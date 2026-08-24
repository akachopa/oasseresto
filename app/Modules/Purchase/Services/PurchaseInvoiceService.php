<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Services;

use App\Modules\Accounting\Events\BusinessDocumentPosted;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Enums\PayableStatus;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Core\Services\LineCalculator;
use App\Modules\Finance\Models\Payable;
use App\Modules\Product\Models\Product;
use App\Modules\Purchase\Models\GoodsReceipt;
use App\Modules\Purchase\Models\GoodsReceiptItem;
use App\Modules\Purchase\Models\PurchaseInvoice;
use App\Modules\Purchase\Models\PurchaseInvoiceItem;
use App\Modules\Purchase\Models\PurchaseOrderItem;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Invoice pembelian mengubah barang yang sudah diterima menjadi kewajiban
 * bayar (PLAN 22). Posting invoice membuat satu baris hutang; nilai hutang
 * tidak pernah ditulis dua kali karena payables unik per dokumen sumber.
 */
class PurchaseInvoiceService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly LineCalculator $calculator,
        private readonly SupplierPerformanceService $performance,
    ) {}

    /**
     * Baris invoice disiapkan dari penerimaan yang belum ditagih, sehingga
     * tagihan supplier selalu bersandar pada barang yang benar-benar masuk.
     *
     * @return array<int, array<string, mixed>>
     */
    public function draftItemsFromReceipt(GoodsReceipt $receipt): array
    {
        $receipt->load('items.product', 'items.orderItem');

        return $receipt->items
            ->filter(fn (GoodsReceiptItem $item) => $item->uninvoicedBaseQuantity() > 0)
            ->map(fn (GoodsReceiptItem $item) => [
                'goods_receipt_item_id' => $item->getKey(),
                'purchase_order_item_id' => $item->purchase_order_item_id,
                'product_id' => $item->product_id,
                'unit_id' => $item->unit_id,
                'quantity' => $item->product->fromBaseQuantity($item->uninvoicedBaseQuantity(), $item->unit_id),
                'unit_price' => $item->orderItem?->unit_price ?? $item->unit_cost,
                'tax_code_id' => $item->orderItem?->tax_code_id ?? $item->product->tax_code_id,
                'note' => $item->note,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, unit_price: float, tax_code_id?: ?int, goods_receipt_item_id?: ?int, purchase_order_item_id?: ?int, discount_amount?: ?float, note?: ?string}>  $items
     */
    public function save(?PurchaseInvoice $invoice, array $attributes, array $items): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoice, $attributes, $items): PurchaseInvoice {
            if ($invoice !== null && ! $invoice->status->isEditable()) {
                throw new RuntimeException('Invoice pembelian yang sudah diposting tidak bisa diubah.');
            }

            $supplier = Supplier::findOrFail($attributes['supplier_id']);
            $date = Carbon::parse($attributes['invoice_date']);
            $term = PaymentTermType::from($attributes['payment_term'] ?? $supplier->payment_term->value);

            $invoice ??= new PurchaseInvoice([
                'status' => DocumentStatus::Draft,
                'created_by' => Auth::id(),
            ]);

            $invoice->fill([
                'supplier_id' => $supplier->getKey(),
                'purchase_order_id' => $attributes['purchase_order_id'] ?? $invoice->purchase_order_id,
                'branch_id' => $attributes['branch_id'] ?? $invoice->branch_id,
                'supplier_invoice_number' => $attributes['supplier_invoice_number'] ?? null,
                'invoice_date' => $date->toDateString(),
                'due_date' => $attributes['due_date'] ?? $date->copy()->addDays($term->days())->toDateString(),
                'payment_term' => $term,
                'other_cost' => (float) ($attributes['other_cost'] ?? 0),
                'note' => $attributes['note'] ?? null,
            ]);

            $invoice->number ??= $this->numbers->next('purchase_invoice', $invoice->branch_id, $date);
            $invoice->save();

            $this->syncItems($invoice, $items);

            return $invoice->refresh();
        });
    }

    /**
     * Posting invoice: hutang terbentuk dan kuantitas yang sudah ditagih
     * dicatat agar satu penerimaan tidak ditagih dua kali.
     */
    public function post(PurchaseInvoice $invoice): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoice): PurchaseInvoice {
            if ($invoice->status !== DocumentStatus::Draft) {
                throw new RuntimeException('Hanya invoice draft yang bisa diposting.');
            }

            $invoice->load('items');

            if ($invoice->items->isEmpty()) {
                throw new RuntimeException('Invoice tanpa baris tidak bisa diposting.');
            }

            foreach ($invoice->items as $item) {
                $this->applyToSources($item);
            }

            $invoice->status = DocumentStatus::Posted;
            $invoice->outstanding_amount = round($invoice->total - $invoice->paid_amount, 4);
            $invoice->posted_by = Auth::id();
            $invoice->posted_at = now();
            $invoice->save();

            $this->createPayable($invoice);
            $this->performance->refresh((int) $invoice->supplier_id);

            $invoice = $invoice->refresh();
            event(new BusinessDocumentPosted('purchase_invoice', $invoice));

            return $invoice;
        });
    }

    public function cancel(PurchaseInvoice $invoice): PurchaseInvoice
    {
        if ($invoice->status !== DocumentStatus::Draft) {
            throw new RuntimeException('Invoice yang sudah diposting hanya bisa dikoreksi lewat retur atau reversal.');
        }

        $invoice->status = DocumentStatus::Cancelled;
        $invoice->save();

        return $invoice;
    }

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, unit_price: float, tax_code_id?: ?int, goods_receipt_item_id?: ?int, purchase_order_item_id?: ?int, discount_amount?: ?float, note?: ?string}>  $items
     */
    private function syncItems(PurchaseInvoice $invoice, array $items): void
    {
        $invoice->items()->delete();

        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;

        foreach ($items as $row) {
            $product = Product::findOrFail($row['product_id']);
            $unitId = (int) ($row['unit_id'] ?: $product->base_unit_id);
            $taxCodeId = $row['tax_code_id'] ?? $product->tax_code_id;
            $taxCode = $taxCodeId ? TaxCode::find($taxCodeId) : null;

            $line = $this->calculator->line(
                quantity: (float) $row['quantity'],
                unitPrice: (float) $row['unit_price'],
                discountAmount: isset($row['discount_amount']) ? (float) $row['discount_amount'] : null,
                taxCode: $taxCode,
            );

            PurchaseInvoiceItem::create([
                'company_id' => $invoice->company_id,
                'purchase_invoice_id' => $invoice->getKey(),
                'goods_receipt_item_id' => $row['goods_receipt_item_id'] ?? null,
                'purchase_order_item_id' => $row['purchase_order_item_id'] ?? null,
                'product_id' => $product->getKey(),
                'unit_id' => $unitId,
                'tax_code_id' => $taxCode?->getKey(),
                'quantity' => $row['quantity'],
                'base_quantity' => $product->toBaseQuantity((float) $row['quantity'], $unitId),
                'unit_price' => $row['unit_price'],
                'discount_amount' => $line['discount_amount'],
                'tax_amount' => $line['tax_amount'],
                'line_total' => $line['line_total'],
                'note' => $row['note'] ?? null,
            ]);

            $subtotal += $line['base_amount'];
            $discountTotal += $line['discount_amount'];
            $taxTotal += $line['tax_amount'];
        }

        $invoice->subtotal = round($subtotal, 4);
        $invoice->discount_amount = round($discountTotal, 4);
        $invoice->tax_amount = round($taxTotal, 4);
        $invoice->total = round($subtotal + $taxTotal + $invoice->other_cost, 4);
        $invoice->outstanding_amount = round($invoice->total - $invoice->paid_amount, 4);
        $invoice->save();
    }

    private function applyToSources(PurchaseInvoiceItem $item): void
    {
        if ($item->goods_receipt_item_id !== null) {
            $receiptItem = GoodsReceiptItem::find($item->goods_receipt_item_id);

            if ($receiptItem !== null) {
                if ($item->base_quantity > $receiptItem->uninvoicedBaseQuantity() + 0.0001) {
                    throw new RuntimeException(
                        "Jumlah tagihan {$item->product?->sku} melebihi barang yang diterima dan belum ditagih."
                    );
                }

                $receiptItem->invoiced_base_quantity = round(
                    $receiptItem->invoiced_base_quantity + $item->base_quantity,
                    4,
                );
                $receiptItem->save();
            }
        }

        if ($item->purchase_order_item_id === null) {
            return;
        }

        $orderItem = PurchaseOrderItem::find($item->purchase_order_item_id);

        if ($orderItem !== null) {
            $orderItem->invoiced_base_quantity = round(
                $orderItem->invoiced_base_quantity + $item->base_quantity,
                4,
            );
            $orderItem->save();
        }
    }

    private function createPayable(PurchaseInvoice $invoice): Payable
    {
        $payable = Payable::firstOrNew([
            'document_type' => 'purchase_invoice',
            'document_id' => $invoice->getKey(),
        ]);

        $payable->company_id = $invoice->company_id;
        $payable->branch_id = $invoice->branch_id;
        $payable->supplier_id = $invoice->supplier_id;
        $payable->document_number = $invoice->number;
        $payable->supplier_document_number = $invoice->supplier_invoice_number;
        $payable->invoice_date = $invoice->invoice_date->toDateString();
        $payable->due_date = $invoice->due_date->toDateString();
        $payable->amount = $invoice->total;
        $payable->paid_amount = $invoice->paid_amount;
        $payable->status = PayableStatus::Open;
        $payable->refreshStatus()->save();

        return $payable;
    }
}
