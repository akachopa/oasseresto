<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Services;

use App\Modules\Accounting\Events\BusinessDocumentPosted;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Enums\InventoryTransactionType;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Core\Services\LineCalculator;
use App\Modules\Finance\Models\Payable;
use App\Modules\Inventory\Services\StockMovement;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Product\Models\Product;
use App\Modules\Purchase\Models\PurchaseInvoice;
use App\Modules\Purchase\Models\PurchaseReturn;
use App\Modules\Purchase\Models\PurchaseReturnItem;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Retur pembelian mengeluarkan barang dari stok dan, bila diselesaikan sebagai
 * credit note, mengurangi hutang ke supplier (PLAN 23). Nilai retur memakai
 * harga pokok batch terkait supaya valuasi persediaan tetap benar.
 */
class PurchaseReturnService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly StockService $stock,
        private readonly LineCalculator $calculator,
        private readonly SupplierPerformanceService $performance,
    ) {}

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, unit_price?: ?float, batch_id?: ?int, tax_code_id?: ?int, note?: ?string}>  $items
     */
    public function save(?PurchaseReturn $return, array $attributes, array $items): PurchaseReturn
    {
        return DB::transaction(function () use ($return, $attributes, $items): PurchaseReturn {
            if ($return !== null && ! $return->status->isEditable()) {
                throw new RuntimeException('Retur yang sudah diposting tidak bisa diubah.');
            }

            $invoice = isset($attributes['purchase_invoice_id'])
                ? PurchaseInvoice::find($attributes['purchase_invoice_id'])
                : null;

            $warehouse = Warehouse::findOrFail($attributes['warehouse_id']);
            $supplier = Supplier::findOrFail($attributes['supplier_id'] ?? $invoice?->supplier_id);
            $date = Carbon::parse($attributes['return_date']);

            $return ??= new PurchaseReturn([
                'status' => DocumentStatus::Draft,
                'created_by' => Auth::id(),
            ]);

            $return->fill([
                'supplier_id' => $supplier->getKey(),
                'warehouse_id' => $warehouse->getKey(),
                'branch_id' => $warehouse->branch_id,
                'purchase_invoice_id' => $invoice?->getKey(),
                'goods_receipt_id' => $attributes['goods_receipt_id'] ?? null,
                'return_date' => $date->toDateString(),
                'reason' => $attributes['reason'] ?? 'damaged',
                'settlement' => $attributes['settlement'] ?? 'credit_note',
                'note' => $attributes['note'] ?? null,
            ]);

            $return->number ??= $this->numbers->next('purchase_return', $warehouse->branch_id, $date);
            $return->save();

            $this->syncItems($return, $items);

            return $return->refresh();
        });
    }

    public function post(PurchaseReturn $return): PurchaseReturn
    {
        return DB::transaction(function () use ($return): PurchaseReturn {
            if ($return->status !== DocumentStatus::Draft) {
                throw new RuntimeException('Hanya retur draft yang bisa diposting.');
            }

            $return->load('items.product', 'invoice');

            if ($return->items->isEmpty()) {
                throw new RuntimeException('Retur tanpa barang tidak bisa diposting.');
            }

            foreach ($return->items as $item) {
                $ledgers = $this->stock->issue(new StockMovement(
                    product: $item->product,
                    warehouseId: (int) $return->warehouse_id,
                    type: InventoryTransactionType::PurchaseReturn,
                    quantity: $item->base_quantity,
                    unitId: (int) $item->product->base_unit_id,
                    batchId: $item->batch_id,
                    documentType: 'purchase_return',
                    documentId: (int) $return->getKey(),
                    documentNumber: $return->number,
                    date: $return->return_date,
                    branchId: $return->branch_id,
                    note: $item->note,
                ));

                $cost = collect($ledgers)->sum('total_cost');

                $item->unit_cost = $item->base_quantity > 0 ? round($cost / $item->base_quantity, 4) : 0.0;
                $item->save();
            }

            $return->status = DocumentStatus::Posted;
            $return->posted_by = Auth::id();
            $return->posted_at = now();
            $return->save();

            if ($return->settlement === 'credit_note') {
                $this->reducePayable($return);
            }

            $this->performance->refresh((int) $return->supplier_id);

            $return = $return->refresh();
            event(new BusinessDocumentPosted('purchase_return', $return));

            return $return;
        });
    }

    public function cancel(PurchaseReturn $return): PurchaseReturn
    {
        if ($return->status !== DocumentStatus::Draft) {
            throw new RuntimeException('Retur yang sudah diposting tidak bisa dibatalkan.');
        }

        $return->status = DocumentStatus::Cancelled;
        $return->save();

        return $return;
    }

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, unit_price?: ?float, batch_id?: ?int, tax_code_id?: ?int, note?: ?string}>  $items
     */
    private function syncItems(PurchaseReturn $return, array $items): void
    {
        $return->items()->delete();

        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($items as $row) {
            $product = Product::findOrFail($row['product_id']);
            $unitId = (int) ($row['unit_id'] ?: $product->base_unit_id);
            $taxCodeId = $row['tax_code_id'] ?? $product->tax_code_id;
            $taxCode = $taxCodeId ? TaxCode::find($taxCodeId) : null;
            $price = (float) ($row['unit_price'] ?? $product->last_purchase_cost ?: $product->average_cost);

            $line = $this->calculator->line(
                quantity: (float) $row['quantity'],
                unitPrice: $price,
                taxCode: $taxCode,
            );

            PurchaseReturnItem::create([
                'company_id' => $return->company_id,
                'purchase_return_id' => $return->getKey(),
                'product_id' => $product->getKey(),
                'unit_id' => $unitId,
                'batch_id' => $row['batch_id'] ?? null,
                'tax_code_id' => $taxCode?->getKey(),
                'quantity' => $row['quantity'],
                'base_quantity' => $product->toBaseQuantity((float) $row['quantity'], $unitId),
                'unit_price' => $price,
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
     * Credit note mengurangi nilai hutang invoice terkait, bukan membuat
     * pembayaran, sehingga aging AP tetap mencerminkan kewajiban riil.
     */
    private function reducePayable(PurchaseReturn $return): void
    {
        if ($return->invoice === null) {
            return;
        }

        $payable = Payable::where('document_type', 'purchase_invoice')
            ->where('document_id', $return->invoice->getKey())
            ->first();

        if ($payable === null) {
            return;
        }

        $reduction = min($return->total, $payable->outstanding_amount);

        $payable->amount = round($payable->amount - $reduction, 4);
        $payable->refreshStatus()->save();

        $invoice = $return->invoice;
        $invoice->outstanding_amount = round($invoice->outstanding_amount - $reduction, 4);
        $invoice->save();
    }
}
