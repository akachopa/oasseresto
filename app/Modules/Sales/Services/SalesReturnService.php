<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Enums\InventoryTransactionType;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Core\Services\LineCalculator;
use App\Modules\Customer\Models\Customer;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Inventory\Services\StockMovement;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Product\Models\Product;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceItem;
use App\Modules\Sales\Models\SalesReturn;
use App\Modules\Sales\Models\SalesReturnItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Retur penjualan memasukkan barang kembali ke stok (bila layak jual) dan,
 * sebagai credit note, mengurangi piutang customer (PLAN 30). Nilai barang
 * yang masuk memakai harga pokok saat dijual supaya laba tidak terdistorsi.
 */
class SalesReturnService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly StockService $stock,
        private readonly LineCalculator $calculator,
        private readonly CreditControlService $credit,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function draftItemsFromInvoice(SalesInvoice $invoice): array
    {
        $invoice->load('items.product');

        return $invoice->items
            ->filter(fn (SalesInvoiceItem $item) => $item->returnableBaseQuantity() > 0)
            ->map(fn (SalesInvoiceItem $item) => [
                'sales_invoice_item_id' => $item->getKey(),
                'product_id' => $item->product_id,
                'unit_id' => $item->unit_id,
                'quantity' => 0,
                'unit_price' => $item->unit_price,
                'unit_cost' => $item->unit_cost,
                'tax_code_id' => $item->tax_code_id,
                'batch_id' => null,
                'note' => null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, unit_price?: ?float, unit_cost?: ?float, sales_invoice_item_id?: ?int, batch_id?: ?int, tax_code_id?: ?int, note?: ?string}>  $items
     */
    public function save(?SalesReturn $return, array $attributes, array $items): SalesReturn
    {
        return DB::transaction(function () use ($return, $attributes, $items): SalesReturn {
            if ($return !== null && ! $return->status->isEditable()) {
                throw new RuntimeException('Retur yang sudah diposting tidak bisa diubah.');
            }

            $invoice = isset($attributes['sales_invoice_id'])
                ? SalesInvoice::find($attributes['sales_invoice_id'])
                : null;

            $customer = Customer::findOrFail($attributes['customer_id'] ?? $invoice?->customer_id);
            $warehouse = Warehouse::findOrFail($attributes['warehouse_id']);
            $date = Carbon::parse($attributes['return_date']);

            $return ??= new SalesReturn([
                'status' => DocumentStatus::Draft,
                'created_by' => Auth::id(),
            ]);

            $return->fill([
                'customer_id' => $customer->getKey(),
                'warehouse_id' => $warehouse->getKey(),
                'branch_id' => $warehouse->branch_id,
                'sales_invoice_id' => $invoice?->getKey(),
                'return_date' => $date->toDateString(),
                'reason' => $attributes['reason'] ?? 'damaged',
                'settlement' => $attributes['settlement'] ?? 'credit_note',
                'restock' => (bool) ($attributes['restock'] ?? true),
                'note' => $attributes['note'] ?? null,
            ]);

            $return->number ??= $this->numbers->next('sales_return', $warehouse->branch_id, $date);
            $return->save();

            $this->syncItems($return, $items);

            return $return->refresh();
        });
    }

    public function post(SalesReturn $return): SalesReturn
    {
        return DB::transaction(function () use ($return): SalesReturn {
            if ($return->status !== DocumentStatus::Draft) {
                throw new RuntimeException('Hanya retur draft yang bisa diposting.');
            }

            $return->load('items.product', 'invoice');

            if ($return->items->isEmpty()) {
                throw new RuntimeException('Retur tanpa barang tidak bisa diposting.');
            }

            $costTotal = 0.0;

            foreach ($return->items as $item) {
                $this->assertWithinInvoice($item);

                /*
                 * Barang rusak tidak dimasukkan ke stok jual; nilainya tetap
                 * dicatat sebagai kerugian lewat harga pokok retur.
                 */
                if ($return->restock) {
                    $ledgers = $this->stock->receive(new StockMovement(
                        product: $item->product,
                        warehouseId: (int) $return->warehouse_id,
                        type: InventoryTransactionType::SaleReturn,
                        quantity: $item->base_quantity,
                        unitId: (int) $item->product->base_unit_id,
                        unitCost: $item->unit_cost,
                        batchId: $item->batch_id,
                        documentType: 'sales_return',
                        documentId: (int) $return->getKey(),
                        documentNumber: $return->number,
                        date: $return->return_date,
                        branchId: $return->branch_id,
                        note: $item->note,
                    ));

                    $item->batch_id ??= $ledgers[0]->batch_id;
                    $item->save();
                }

                $this->applyToInvoiceItem($item);

                $costTotal += $item->base_quantity * $item->unit_cost;
            }

            $return->status = DocumentStatus::Posted;
            $return->cost_of_goods = round($costTotal, 4);
            $return->posted_by = Auth::id();
            $return->posted_at = now();
            $return->save();

            if ($return->settlement === 'credit_note') {
                $this->reduceReceivable($return);
            }

            if ($return->customer !== null) {
                $this->credit->refreshCustomer($return->customer);
            }

            return $return->refresh();
        });
    }

    public function cancel(SalesReturn $return): SalesReturn
    {
        if ($return->status !== DocumentStatus::Draft) {
            throw new RuntimeException('Retur yang sudah diposting tidak bisa dibatalkan.');
        }

        $return->status = DocumentStatus::Cancelled;
        $return->save();

        return $return;
    }

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, unit_price?: ?float, unit_cost?: ?float, sales_invoice_item_id?: ?int, batch_id?: ?int, tax_code_id?: ?int, note?: ?string}>  $items
     */
    private function syncItems(SalesReturn $return, array $items): void
    {
        $return->items()->delete();

        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($items as $row) {
            $quantity = (float) $row['quantity'];

            if ($quantity <= 0) {
                continue;
            }

            $product = Product::findOrFail($row['product_id']);
            $unitId = (int) ($row['unit_id'] ?: $product->base_unit_id);
            $taxCodeId = $row['tax_code_id'] ?? $product->tax_code_id;
            $taxCode = $taxCodeId ? TaxCode::find($taxCodeId) : null;
            $invoiceItem = isset($row['sales_invoice_item_id'])
                ? SalesInvoiceItem::find($row['sales_invoice_item_id'])
                : null;

            $price = (float) ($row['unit_price'] ?? $invoiceItem?->unit_price ?? $product->base_price);
            $cost = (float) ($row['unit_cost'] ?? $invoiceItem?->unit_cost ?? $product->average_cost);

            $line = $this->calculator->line(
                quantity: $quantity,
                unitPrice: $price,
                taxCode: $taxCode,
            );

            SalesReturnItem::create([
                'company_id' => $return->company_id,
                'sales_return_id' => $return->getKey(),
                'sales_invoice_item_id' => $invoiceItem?->getKey(),
                'product_id' => $product->getKey(),
                'unit_id' => $unitId,
                'batch_id' => $row['batch_id'] ?? null,
                'tax_code_id' => $taxCode?->getKey(),
                'quantity' => $quantity,
                'base_quantity' => $product->toBaseQuantity($quantity, $unitId),
                'unit_price' => $price,
                'unit_cost' => $cost,
                'tax_amount' => $line['tax_amount'],
                'line_total' => $line['line_total'],
                'note' => $row['note'] ?? null,
            ]);

            $subtotal += $line['base_amount'];
            $taxTotal += $line['tax_amount'];
        }

        $return->subtotal = round($subtotal, 4);
        $return->tax_amount = round($taxTotal, 4);
        $return->total = round($subtotal + $taxTotal, 4);
        $return->save();
    }

    /**
     * Retur tidak boleh melebihi yang pernah ditagih, supaya stok dan piutang
     * tidak bisa dibengkokkan lewat retur berulang.
     */
    private function assertWithinInvoice(SalesReturnItem $item): void
    {
        $invoiceItem = $item->invoiceItem;

        if ($invoiceItem === null) {
            return;
        }

        if ($item->base_quantity > $invoiceItem->returnableBaseQuantity() + 0.0001) {
            throw new RuntimeException(
                "Retur {$item->product->sku} melebihi jumlah yang pernah ditagih."
            );
        }
    }

    private function applyToInvoiceItem(SalesReturnItem $item): void
    {
        $invoiceItem = $item->invoiceItem;

        if ($invoiceItem === null) {
            return;
        }

        $invoiceItem->returned_base_quantity = round(
            $invoiceItem->returned_base_quantity + $item->base_quantity,
            4,
        );
        $invoiceItem->save();
    }

    /**
     * Credit note mengurangi nilai piutang invoice terkait, bukan membuat
     * pembayaran, sehingga aging AR tetap mencerminkan hak tagih riil.
     */
    private function reduceReceivable(SalesReturn $return): void
    {
        if ($return->invoice === null) {
            return;
        }

        $invoice = $return->invoice;

        $receivable = Receivable::where('document_type', 'sales_invoice')
            ->where('document_id', $invoice->getKey())
            ->first();

        $reduction = $receivable !== null
            ? min($return->total, $receivable->outstanding_amount)
            : min($return->total, $invoice->outstanding_amount);

        if ($receivable !== null) {
            $receivable->amount = round($receivable->amount - $reduction, 4);
            $receivable->refreshStatus()->save();
        }

        $invoice->outstanding_amount = round($invoice->outstanding_amount - $reduction, 4);
        $invoice->save();
    }
}
