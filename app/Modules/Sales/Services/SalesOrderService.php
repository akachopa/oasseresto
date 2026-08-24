<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Services\ApprovalService;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Core\Services\LineCalculator;
use App\Modules\Customer\Models\Customer;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Services\MarginGuard;
use App\Modules\Product\Services\PricingService;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Models\QuotationItem;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sales order adalah komitmen ke customer (PLAN 26). Saat disetujui, stok
 * direservasi sehingga barang yang sama tidak dijanjikan dua kali, dan
 * pemeriksaan kredit serta margin dijalankan sebelum order bisa jalan.
 */
class SalesOrderService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly LineCalculator $calculator,
        private readonly PricingService $pricing,
        private readonly MarginGuard $margin,
        private readonly CreditControlService $credit,
        private readonly ApprovalService $approvals,
        private readonly StockService $stock,
        private readonly QuotationService $quotations,
    ) {}

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, unit_price?: ?float, discount_percent?: ?float, tax_code_id?: ?int, quotation_item_id?: ?int, note?: ?string}>  $items
     */
    public function save(?SalesOrder $order, array $attributes, array $items): SalesOrder
    {
        return DB::transaction(function () use ($order, $attributes, $items): SalesOrder {
            if ($order !== null && ! $order->status->isEditable()) {
                throw new RuntimeException('Sales order yang sudah diajukan tidak bisa diubah.');
            }

            $customer = Customer::findOrFail($attributes['customer_id']);
            $warehouse = Warehouse::findOrFail($attributes['warehouse_id']);
            $date = Carbon::parse($attributes['order_date']);

            $order ??= new SalesOrder([
                'status' => DocumentStatus::Draft,
                'created_by' => Auth::id(),
            ]);

            $order->fill([
                'customer_id' => $customer->getKey(),
                'customer_address_id' => $attributes['customer_address_id'] ?? null,
                'warehouse_id' => $warehouse->getKey(),
                'branch_id' => $warehouse->branch_id,
                'quotation_id' => $attributes['quotation_id'] ?? $order->quotation_id,
                'order_date' => $date->toDateString(),
                'requested_delivery_date' => $attributes['requested_delivery_date'] ?? null,
                'source' => $attributes['source'] ?? $order->source ?? 'manual',
                'payment_term' => $attributes['payment_term'] ?? $customer->effectivePaymentTerm(),
                'is_tax_inclusive' => (bool) ($attributes['is_tax_inclusive'] ?? false),
                'shipping_cost' => (float) ($attributes['shipping_cost'] ?? 0),
                'salesman_id' => $attributes['salesman_id'] ?? $customer->salesman_id ?? Auth::id(),
                'note' => $attributes['note'] ?? null,
            ]);

            $order->number ??= $this->numbers->next('sales_order', $warehouse->branch_id, $date);
            $order->save();

            $this->syncItems($order, $items);

            return $order->refresh();
        });
    }

    /**
     * Buat order dari penawaran yang masih berlaku, dengan harga yang sudah
     * dijanjikan ke customer.
     */
    public function fromQuotation(Quotation $quotation, array $attributes = []): SalesOrder
    {
        if (! $quotation->isConvertible()) {
            throw new RuntimeException('Penawaran ini belum dikirim atau sudah kedaluwarsa.');
        }

        $quotation->load('items');

        $items = $quotation->items
            ->map(fn (QuotationItem $item) => [
                'product_id' => $item->product_id,
                'unit_id' => $item->unit_id,
                'quotation_item_id' => $item->getKey(),
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_percent' => $item->discount_percent,
                'tax_code_id' => $item->tax_code_id,
                'note' => $item->note,
            ])
            ->values()
            ->all();

        if ($items === []) {
            throw new RuntimeException('Penawaran tanpa barang tidak bisa dijadikan order.');
        }

        $order = $this->save(null, array_merge([
            'customer_id' => $quotation->customer_id,
            'warehouse_id' => $quotation->warehouse_id ?? $this->defaultWarehouseId($quotation->branch_id),
            'order_date' => now()->toDateString(),
            'quotation_id' => $quotation->getKey(),
            'payment_term' => $quotation->payment_term->value,
            'is_tax_inclusive' => $quotation->is_tax_inclusive,
            'salesman_id' => $quotation->salesman_id,
        ], $attributes), $items);

        $this->quotations->markConverted($quotation);

        return $order;
    }

    /**
     * Ajukan order: kredit dan margin diperiksa lebih dulu. Order yang lolos
     * tanpa aturan approval langsung disetujui dan stoknya direservasi.
     */
    public function submit(SalesOrder $order): SalesOrder
    {
        if (! $order->status->isEditable()) {
            throw new RuntimeException('Sales order ini sudah diajukan.');
        }

        $order->load('items.product', 'customer');

        if ($order->items->isEmpty()) {
            throw new RuntimeException('Sales order tanpa barang tidak bisa diajukan.');
        }

        $decision = $this->credit->evaluate(
            $order->customer,
            (float) $order->total,
            $order->payment_term,
            $order->getKey(),
        );

        /*
         * Hasil pemeriksaan kredit disimpan di luar transaksi pengajuan supaya
         * alasan blokir tetap tercatat walau pengajuannya dibatalkan.
         */
        $order->credit_status = $decision->status;
        $order->credit_note = $decision->message;
        $order->save();

        if ($decision->isBlocked()) {
            throw new RuntimeException($decision->message ?? 'Order diblokir oleh kontrol kredit.');
        }

        return DB::transaction(function () use ($order, $decision): SalesOrder {
            $order->status = DocumentStatus::Submitted;
            $order->submitted_at = now();
            $order->save();

            $approval = $this->approvals->request(
                $order,
                $this->approvalTrigger($order, $decision),
                $decision->message ?? $order->margin_note,
            );

            if ($approval === null) {
                $this->approveWithoutRules($order);
            }

            return $order->refresh();
        });
    }

    public function approve(SalesOrder $order, ?string $note = null): SalesOrder
    {
        $approval = $this->openApproval($order);

        if ($approval === null) {
            if ($order->status !== DocumentStatus::Submitted) {
                throw new RuntimeException('Hanya sales order yang diajukan bisa disetujui.');
            }

            $this->approveWithoutRules($order);

            return $order->refresh();
        }

        $this->approvals->approve($approval, Auth::user(), $note);

        return $order->refresh();
    }

    /**
     * Dipanggil oleh callback approval setelah seluruh langkah selesai.
     */
    public function handleApproved(SalesOrder $order): void
    {
        $this->reserveStock($order);
    }

    public function reject(SalesOrder $order, ?string $reason = null): SalesOrder
    {
        $approval = $this->openApproval($order);

        if ($approval !== null) {
            $this->approvals->reject($approval, Auth::user(), $reason);

            return $order->refresh();
        }

        $order->status = DocumentStatus::Rejected;
        $order->rejection_reason = $reason;
        $order->save();

        return $order;
    }

    public function cancel(SalesOrder $order): SalesOrder
    {
        return DB::transaction(function () use ($order): SalesOrder {
            if ($order->deliveries()->exists()) {
                throw new RuntimeException('Sales order yang sudah ada pengirimannya tidak bisa dibatalkan.');
            }

            $this->approvals->cancelOpenRequests($order);
            $this->releaseStock($order);

            $order->status = DocumentStatus::Cancelled;
            $order->save();

            return $order;
        });
    }

    /**
     * Tutup order saat sisa barang tidak akan dikirim, sekaligus melepas
     * reservasi supaya stok bisa dijual ke customer lain.
     */
    public function close(SalesOrder $order): SalesOrder
    {
        return DB::transaction(function () use ($order): SalesOrder {
            if (! in_array($order->status, [DocumentStatus::Approved, DocumentStatus::PartiallyProcessed], true)) {
                throw new RuntimeException('Hanya sales order berjalan yang bisa ditutup.');
            }

            $this->releaseStock($order);

            $order->status = DocumentStatus::Completed;
            $order->closed_at = now();
            $order->save();

            return $order;
        });
    }

    /**
     * Status order ditentukan oleh kuantitas yang benar-benar terkirim.
     */
    public function refreshDeliveryStatus(SalesOrder $order): SalesOrder
    {
        $order->load('items');

        $delivered = $order->items->sum('delivered_base_quantity');

        if ($delivered <= 0) {
            return $order;
        }

        $outstanding = $order->outstandingBaseQuantity();

        $order->status = $outstanding > 0 ? DocumentStatus::PartiallyProcessed : DocumentStatus::Completed;
        $order->closed_at = $outstanding > 0 ? null : now();
        $order->save();

        return $order;
    }

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, unit_price?: ?float, discount_percent?: ?float, tax_code_id?: ?int, quotation_item_id?: ?int, note?: ?string}>  $items
     */
    private function syncItems(SalesOrder $order, array $items): void
    {
        $order->items()->delete();
        $customer = $order->customer;

        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;
        $costTotal = 0.0;
        $marginNotes = [];
        $requiresMarginApproval = false;

        foreach ($items as $row) {
            $product = Product::findOrFail($row['product_id']);
            $unitId = (int) ($row['unit_id'] ?: $product->base_unit_id);
            $taxCodeId = $row['tax_code_id'] ?? $product->tax_code_id;
            $taxCode = $taxCodeId ? TaxCode::find($taxCodeId) : null;

            $quote = $this->pricing->quote(
                product: $product,
                quantity: (float) $row['quantity'],
                customer: $customer,
                unitId: $unitId,
                branchId: $order->branch_id,
                paymentTerm: $order->payment_term instanceof PaymentTermType ? $order->payment_term : null,
                date: $order->order_date,
            );

            $unitPrice = isset($row['unit_price']) && $row['unit_price'] !== null && $row['unit_price'] !== ''
                ? (float) $row['unit_price']
                : $quote->price;

            $line = $this->calculator->line(
                quantity: (float) $row['quantity'],
                unitPrice: $unitPrice,
                discountPercent: (float) ($row['discount_percent'] ?? 0),
                taxCode: $taxCode,
                taxInclusive: (bool) $order->is_tax_inclusive,
            );

            $quantity = (float) $row['quantity'];
            $netUnitPrice = $quantity > 0 ? round(($quantity * $unitPrice - $line['discount_amount']) / $quantity, 4) : 0.0;
            $verdict = $this->margin->evaluate($product, $netUnitPrice, $quote->unitCost);

            if (! $verdict['allowed']) {
                throw new RuntimeException((string) $verdict['message']);
            }

            if ($verdict['requires_approval']) {
                $requiresMarginApproval = true;
                $marginNotes[] = (string) $verdict['message'];
            }

            SalesOrderItem::create([
                'company_id' => $order->company_id,
                'sales_order_id' => $order->getKey(),
                'quotation_item_id' => $row['quotation_item_id'] ?? null,
                'product_id' => $product->getKey(),
                'unit_id' => $unitId,
                'tax_code_id' => $taxCode?->getKey(),
                'quantity' => $quantity,
                'base_quantity' => $product->toBaseQuantity($quantity, $unitId),
                'list_price' => $quote->listPrice,
                'unit_price' => $unitPrice,
                'unit_cost' => $quote->unitCost,
                'discount_percent' => $row['discount_percent'] ?? 0,
                'discount_amount' => $line['discount_amount'],
                'tax_amount' => $line['tax_amount'],
                'line_total' => $line['line_total'],
                'margin_percent' => $verdict['margin_percent'],
                'note' => $row['note'] ?? null,
            ]);

            $subtotal += $line['base_amount'];
            $discountTotal += $line['discount_amount'];
            $taxTotal += $line['tax_amount'];
            $costTotal += $quantity * $quote->unitCost;
        }

        $order->subtotal = round($subtotal, 4);
        $order->discount_amount = round($discountTotal, 4);
        $order->tax_amount = round($taxTotal, 4);
        $order->estimated_cost = round($costTotal, 4);
        $order->total = round($subtotal + $taxTotal + $order->shipping_cost, 4);
        $order->requires_margin_approval = $requiresMarginApproval;
        $order->margin_note = $marginNotes === [] ? null : implode(' ', $marginNotes);
        $order->save();
    }

    /**
     * Pemicu approval menentukan aturan mana yang dipakai: batas kredit,
     * margin di bawah minimum, atau approval bernilai biasa.
     */
    private function approvalTrigger(SalesOrder $order, CreditDecision $decision): string
    {
        if ($decision->requiresApproval()) {
            return 'credit_limit';
        }

        return $order->requires_margin_approval ? 'margin' : 'always';
    }

    private function approveWithoutRules(SalesOrder $order): void
    {
        $order->status = DocumentStatus::Approved;
        $order->approved_by = Auth::id();
        $order->approved_at = now();
        $order->save();

        $this->reserveStock($order);
    }

    /**
     * Reservasi dicatat per baris supaya pelepasannya bisa tepat, termasuk
     * saat order dibatalkan sebagian.
     */
    private function reserveStock(SalesOrder $order): void
    {
        $order->load('items');

        foreach ($order->items as $item) {
            $target = $item->outstandingBaseQuantity();
            $delta = round($target - $item->reserved_base_quantity, 4);

            if ($delta <= 0) {
                continue;
            }

            $this->stock->reserve((int) $item->product_id, (int) $order->warehouse_id, $delta);

            $item->reserved_base_quantity = round($item->reserved_base_quantity + $delta, 4);
            $item->save();
        }
    }

    private function releaseStock(SalesOrder $order): void
    {
        $order->load('items');

        foreach ($order->items as $item) {
            if ($item->reserved_base_quantity <= 0) {
                continue;
            }

            $this->stock->release((int) $item->product_id, (int) $order->warehouse_id, $item->reserved_base_quantity);

            $item->reserved_base_quantity = 0;
            $item->save();
        }
    }

    private function defaultWarehouseId(?int $branchId): int
    {
        $warehouseId = Warehouse::active()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderByDesc('is_default')
            ->value('id');

        if ($warehouseId === null) {
            throw new RuntimeException('Belum ada gudang aktif untuk cabang ini.');
        }

        return (int) $warehouseId;
    }

    private function openApproval(SalesOrder $order): ?ApprovalRequest
    {
        return ApprovalRequest::where('document_type', $order->approvalDocumentType())
            ->where('document_id', $order->getKey())
            ->pending()
            ->with('steps')
            ->first();
    }
}
