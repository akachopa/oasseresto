<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Services;

use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Services\ApprovalService;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Product\Models\Product;
use App\Modules\Purchase\Models\PurchaseRequest;
use App\Modules\Purchase\Models\PurchaseRequestItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Purchase request adalah permintaan barang dari gudang atau cabang, belum
 * komitmen ke supplier (PLAN 18). PR baru bisa jadi PO setelah disetujui.
 */
class PurchaseRequestService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly ApprovalService $approvals,
    ) {}

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, estimated_price?: ?float, suggested_supplier_id?: ?int, note?: ?string}>  $items
     */
    public function save(?PurchaseRequest $request, array $attributes, array $items): PurchaseRequest
    {
        return DB::transaction(function () use ($request, $attributes, $items): PurchaseRequest {
            if ($request !== null && ! $request->status->isEditable()) {
                throw new RuntimeException('Purchase request yang sudah diajukan tidak bisa diubah.');
            }

            $warehouse = Warehouse::findOrFail($attributes['warehouse_id']);
            $date = Carbon::parse($attributes['request_date']);

            $request ??= new PurchaseRequest([
                'status' => DocumentStatus::Draft,
                'created_by' => Auth::id(),
            ]);

            $request->fill([
                'warehouse_id' => $warehouse->getKey(),
                'branch_id' => $warehouse->branch_id,
                'request_date' => $date->toDateString(),
                'needed_date' => $attributes['needed_date'] ?? null,
                'priority' => $attributes['priority'] ?? 'normal',
                'note' => $attributes['note'] ?? null,
            ]);

            $request->number ??= $this->numbers->next('purchase_request', $warehouse->branch_id, $date);
            $request->save();

            $this->syncItems($request, $items);

            return $request->refresh();
        });
    }

    /**
     * Ajukan PR. Bila tidak ada aturan approval yang cocok, PR langsung
     * disetujui supaya alur kerja tidak terhenti tanpa sebab.
     */
    public function submit(PurchaseRequest $request): PurchaseRequest
    {
        return DB::transaction(function () use ($request): PurchaseRequest {
            if (! $request->status->isEditable()) {
                throw new RuntimeException('Purchase request ini sudah diajukan.');
            }

            $request->load('items');

            if ($request->items->isEmpty()) {
                throw new RuntimeException('Purchase request tanpa barang tidak bisa diajukan.');
            }

            $request->status = DocumentStatus::Submitted;
            $request->submitted_at = now();
            $request->save();

            $approval = $this->approvals->request($request);

            if ($approval === null) {
                $request->status = DocumentStatus::Approved;
                $request->approved_by = Auth::id();
                $request->approved_at = now();
                $request->save();
            }

            return $request->refresh();
        });
    }

    public function approve(PurchaseRequest $request, ?string $note = null): PurchaseRequest
    {
        $approval = $this->openApproval($request);

        if ($approval === null) {
            if ($request->status !== DocumentStatus::Submitted) {
                throw new RuntimeException('Hanya purchase request yang diajukan bisa disetujui.');
            }

            $request->status = DocumentStatus::Approved;
            $request->approved_by = Auth::id();
            $request->approved_at = now();
            $request->save();

            return $request;
        }

        $this->approvals->approve($approval, Auth::user(), $note);

        return $request->refresh();
    }

    public function reject(PurchaseRequest $request, ?string $reason = null): PurchaseRequest
    {
        $approval = $this->openApproval($request);

        if ($approval !== null) {
            $this->approvals->reject($approval, Auth::user(), $reason);

            return $request->refresh();
        }

        $request->status = DocumentStatus::Rejected;
        $request->rejection_reason = $reason;
        $request->save();

        return $request;
    }

    public function cancel(PurchaseRequest $request): PurchaseRequest
    {
        if ($request->orders()->exists()) {
            throw new RuntimeException('Purchase request yang sudah punya PO tidak bisa dibatalkan.');
        }

        $this->approvals->cancelOpenRequests($request);

        $request->status = DocumentStatus::Cancelled;
        $request->save();

        return $request;
    }

    /**
     * Perbarui status PR mengikuti jumlah yang sudah dipesan lewat PO.
     */
    public function refreshFulfillment(PurchaseRequest $request): PurchaseRequest
    {
        $request->load('items');

        $outstanding = $request->outstandingBaseQuantity();

        if ($outstanding <= 0) {
            $request->status = DocumentStatus::Completed;
        } elseif ($request->items->sum('ordered_base_quantity') > 0) {
            $request->status = DocumentStatus::PartiallyProcessed;
        }

        $request->save();

        return $request;
    }

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, estimated_price?: ?float, suggested_supplier_id?: ?int, note?: ?string}>  $items
     */
    private function syncItems(PurchaseRequest $request, array $items): void
    {
        $request->items()->delete();
        $estimated = 0.0;

        foreach ($items as $row) {
            $product = Product::findOrFail($row['product_id']);
            $unitId = (int) ($row['unit_id'] ?: $product->base_unit_id);
            $quantity = (float) $row['quantity'];
            $price = (float) ($row['estimated_price'] ?? $product->last_purchase_cost ?: 0);

            PurchaseRequestItem::create([
                'company_id' => $request->company_id,
                'purchase_request_id' => $request->getKey(),
                'product_id' => $product->getKey(),
                'unit_id' => $unitId,
                'suggested_supplier_id' => $row['suggested_supplier_id'] ?? null,
                'quantity' => $quantity,
                'base_quantity' => $product->toBaseQuantity($quantity, $unitId),
                'estimated_price' => $price,
                'note' => $row['note'] ?? null,
            ]);

            $estimated += $quantity * $price;
        }

        $request->estimated_total = round($estimated, 4);
        $request->save();
    }

    private function openApproval(PurchaseRequest $request): ?ApprovalRequest
    {
        return ApprovalRequest::where('document_type', $request->approvalDocumentType())
            ->where('document_id', $request->getKey())
            ->pending()
            ->with('steps')
            ->first();
    }
}
