<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Services;

use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Services\ApprovalService;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Product\Models\Product;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Purchase\Models\PurchaseOrderItem;
use App\Modules\Purchase\Models\PurchaseRequest;
use App\Modules\Purchase\Models\PurchaseRequestItem;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Supplier\Models\SupplierPriceHistory;
use App\Modules\Supplier\Models\SupplierProduct;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Purchase order adalah komitmen resmi ke supplier (PLAN 19). Setelah
 * disetujui, item PO menjadi acuan tunggal untuk penerimaan dan invoice.
 */
class PurchaseOrderService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly ApprovalService $approvals,
        private readonly LineCalculator $calculator,
        private readonly PurchaseRequestService $requests,
    ) {}

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, unit_price: float, discount_percent?: ?float, tax_code_id?: ?int, purchase_request_item_id?: ?int, note?: ?string}>  $items
     */
    public function save(?PurchaseOrder $order, array $attributes, array $items): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $attributes, $items): PurchaseOrder {
            if ($order !== null && ! $order->status->isEditable()) {
                throw new RuntimeException('Purchase order yang sudah diajukan tidak bisa diubah.');
            }

            $supplier = Supplier::findOrFail($attributes['supplier_id']);
            $warehouse = Warehouse::findOrFail($attributes['warehouse_id']);
            $date = Carbon::parse($attributes['order_date']);

            $order ??= new PurchaseOrder([
                'status' => DocumentStatus::Draft,
                'created_by' => Auth::id(),
            ]);

            $order->fill([
                'supplier_id' => $supplier->getKey(),
                'warehouse_id' => $warehouse->getKey(),
                'branch_id' => $warehouse->branch_id,
                'purchase_request_id' => $attributes['purchase_request_id'] ?? $order->purchase_request_id,
                'order_date' => $date->toDateString(),
                'expected_date' => $attributes['expected_date']
                    ?? $date->copy()->addDays($supplier->lead_time_days)->toDateString(),
                'payment_term' => $attributes['payment_term'] ?? $supplier->payment_term ?? PaymentTermType::Net30,
                'is_tax_inclusive' => (bool) ($attributes['is_tax_inclusive'] ?? false),
                'other_cost' => (float) ($attributes['other_cost'] ?? 0),
                'note' => $attributes['note'] ?? null,
                'terms' => $attributes['terms'] ?? null,
            ]);

            $order->number ??= $this->numbers->next('purchase_order', $warehouse->branch_id, $date);
            $order->save();

            $this->syncItems($order, $items);

            return $order->refresh();
        });
    }

    /**
     * Buat PO dari PR yang sudah disetujui. Hanya sisa yang belum dipesan
     * yang dibawa, sehingga satu PR bisa dipecah ke beberapa supplier.
     */
    public function fromRequest(PurchaseRequest $request, int $supplierId, array $attributes = []): PurchaseOrder
    {
        if (! $request->isOrderable()) {
            throw new RuntimeException('Purchase request ini belum disetujui.');
        }

        $request->load('items.product');

        $items = $request->items
            ->filter(fn (PurchaseRequestItem $item) => $item->outstandingBaseQuantity() > 0)
            ->map(fn (PurchaseRequestItem $item) => [
                'product_id' => $item->product_id,
                'unit_id' => $item->unit_id,
                'purchase_request_item_id' => $item->getKey(),
                'quantity' => $item->product->fromBaseQuantity($item->outstandingBaseQuantity(), $item->unit_id),
                'unit_price' => $item->estimated_price,
                'note' => $item->note,
            ])
            ->values()
            ->all();

        if ($items === []) {
            throw new RuntimeException('Seluruh barang pada purchase request ini sudah dipesan.');
        }

        return $this->save(null, array_merge([
            'supplier_id' => $supplierId,
            'warehouse_id' => $request->warehouse_id,
            'order_date' => now()->toDateString(),
            'purchase_request_id' => $request->getKey(),
        ], $attributes), $items);
    }

    public function submit(PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($order): PurchaseOrder {
            if (! $order->status->isEditable()) {
                throw new RuntimeException('Purchase order ini sudah diajukan.');
            }

            $order->load('items');

            if ($order->items->isEmpty()) {
                throw new RuntimeException('Purchase order tanpa barang tidak bisa diajukan.');
            }

            $order->status = DocumentStatus::Submitted;
            $order->submitted_at = now();
            $order->save();

            $approval = $this->approvals->request($order);

            if ($approval === null) {
                $order->status = DocumentStatus::Approved;
                $order->approved_by = Auth::id();
                $order->approved_at = now();
                $order->save();
            }

            $this->markRequestOrdered($order);

            return $order->refresh();
        });
    }

    public function approve(PurchaseOrder $order, ?string $note = null): PurchaseOrder
    {
        $approval = $this->openApproval($order);

        if ($approval === null) {
            if ($order->status !== DocumentStatus::Submitted) {
                throw new RuntimeException('Hanya purchase order yang diajukan bisa disetujui.');
            }

            $order->status = DocumentStatus::Approved;
            $order->approved_by = Auth::id();
            $order->approved_at = now();
            $order->save();

            return $order;
        }

        $this->approvals->approve($approval, Auth::user(), $note);

        return $order->refresh();
    }

    public function reject(PurchaseOrder $order, ?string $reason = null): PurchaseOrder
    {
        $approval = $this->openApproval($order);

        if ($approval !== null) {
            $this->approvals->reject($approval, Auth::user(), $reason);
        } else {
            $order->status = DocumentStatus::Rejected;
            $order->rejection_reason = $reason;
            $order->save();
        }

        $this->releaseRequest($order);

        return $order->refresh();
    }

    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->receipts()->exists()) {
            throw new RuntimeException('Purchase order yang sudah ada penerimaannya tidak bisa dibatalkan.');
        }

        $this->approvals->cancelOpenRequests($order);

        $order->status = DocumentStatus::Cancelled;
        $order->save();

        $this->releaseRequest($order);

        return $order;
    }

    /**
     * Tutup PO secara manual saat sisa barang tidak akan dikirim supplier,
     * agar tidak selamanya tampil sebagai backorder (PLAN 19).
     */
    public function close(PurchaseOrder $order): PurchaseOrder
    {
        if (! in_array($order->status, [DocumentStatus::Approved, DocumentStatus::PartiallyProcessed], true)) {
            throw new RuntimeException('Hanya purchase order berjalan yang bisa ditutup.');
        }

        $order->status = DocumentStatus::Completed;
        $order->closed_at = now();
        $order->save();

        return $order;
    }

    /**
     * Status PO ditentukan oleh kuantitas yang sudah diterima, bukan disetel
     * manual, supaya backorder selalu akurat.
     */
    public function refreshReceiptStatus(PurchaseOrder $order): PurchaseOrder
    {
        $order->load('items');

        $received = $order->items->sum('received_base_quantity');
        $outstanding = $order->outstandingBaseQuantity();

        if ($received <= 0) {
            return $order;
        }

        $order->status = $outstanding > 0 ? DocumentStatus::PartiallyProcessed : DocumentStatus::Completed;
        $order->closed_at = $outstanding > 0 ? null : now();
        $order->save();

        return $order;
    }

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, unit_price: float, discount_percent?: ?float, tax_code_id?: ?int, purchase_request_item_id?: ?int, note?: ?string}>  $items
     */
    private function syncItems(PurchaseOrder $order, array $items): void
    {
        $order->items()->delete();

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
                discountPercent: (float) ($row['discount_percent'] ?? 0),
                taxCode: $taxCode,
                taxInclusive: (bool) $order->is_tax_inclusive,
            );

            PurchaseOrderItem::create([
                'company_id' => $order->company_id,
                'purchase_order_id' => $order->getKey(),
                'purchase_request_item_id' => $row['purchase_request_item_id'] ?? null,
                'product_id' => $product->getKey(),
                'unit_id' => $unitId,
                'tax_code_id' => $taxCode?->getKey(),
                'quantity' => $row['quantity'],
                'base_quantity' => $product->toBaseQuantity((float) $row['quantity'], $unitId),
                'unit_price' => $row['unit_price'],
                'discount_percent' => $row['discount_percent'] ?? 0,
                'discount_amount' => $line['discount_amount'],
                'tax_amount' => $line['tax_amount'],
                'line_total' => $line['line_total'],
                'note' => $row['note'] ?? null,
            ]);

            $subtotal += $line['base_amount'];
            $discountTotal += $line['discount_amount'];
            $taxTotal += $line['tax_amount'];
        }

        $order->subtotal = round($subtotal, 4);
        $order->discount_amount = round($discountTotal, 4);
        $order->tax_amount = round($taxTotal, 4);
        $order->total = round($subtotal + $taxTotal + $order->other_cost, 4);
        $order->save();
    }

    /**
     * Catat kuantitas PR yang sudah terpesan dan simpan harga supplier
     * terakhir agar rekomendasi harga berikutnya lebih akurat (PLAN 21).
     */
    private function markRequestOrdered(PurchaseOrder $order): void
    {
        $order->load('items.product', 'request');

        foreach ($order->items as $item) {
            if ($item->purchase_request_item_id !== null) {
                $requestItem = PurchaseRequestItem::find($item->purchase_request_item_id);

                if ($requestItem !== null) {
                    $requestItem->ordered_base_quantity = round(
                        $requestItem->ordered_base_quantity + $item->base_quantity,
                        4,
                    );
                    $requestItem->save();
                }
            }

            $this->rememberSupplierPrice($order, $item);
        }

        if ($order->request !== null) {
            $this->requests->refreshFulfillment($order->request);
        }
    }

    private function releaseRequest(PurchaseOrder $order): void
    {
        $order->load('items', 'request');

        foreach ($order->items as $item) {
            if ($item->purchase_request_item_id === null) {
                continue;
            }

            $requestItem = PurchaseRequestItem::find($item->purchase_request_item_id);

            if ($requestItem === null) {
                continue;
            }

            $requestItem->ordered_base_quantity = max(
                0,
                round($requestItem->ordered_base_quantity - $item->base_quantity, 4),
            );
            $requestItem->save();
        }

        if ($order->request !== null) {
            $order->request->load('items');

            $order->request->status = $order->request->items->sum('ordered_base_quantity') > 0
                ? DocumentStatus::PartiallyProcessed
                : DocumentStatus::Approved;
            $order->request->save();
        }
    }

    private function rememberSupplierPrice(PurchaseOrder $order, PurchaseOrderItem $item): void
    {
        SupplierPriceHistory::create([
            'company_id' => $order->company_id,
            'supplier_id' => $order->supplier_id,
            'product_id' => $item->product_id,
            'unit_id' => $item->unit_id,
            'effective_date' => $order->order_date->toDateString(),
            'price' => $item->unit_price,
            'source' => 'purchase_order',
            'reference_number' => $order->number,
        ]);

        $link = SupplierProduct::firstOrNew([
            'supplier_id' => $order->supplier_id,
            'product_id' => $item->product_id,
        ]);

        $link->company_id = $order->company_id;
        $link->unit_id = $item->unit_id;
        $link->last_price = $item->unit_price;
        $link->save();
    }

    private function openApproval(PurchaseOrder $order): ?ApprovalRequest
    {
        return ApprovalRequest::where('document_type', $order->approvalDocumentType())
            ->where('document_id', $order->getKey())
            ->pending()
            ->with('steps')
            ->first();
    }
}
