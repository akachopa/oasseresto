<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Models;

use App\Models\User;
use App\Modules\Approval\Contracts\Approvable;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends BaseModel implements Approvable
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'payment_term' => PaymentTermType::class,
            'order_date' => 'date',
            'expected_date' => 'date',
            'is_tax_inclusive' => 'boolean',
            'subtotal' => 'float',
            'discount_amount' => 'float',
            'tax_amount' => 'float',
            'other_cost' => 'float',
            'total' => 'float',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Penerimaan hanya boleh atas PO yang sudah disetujui dan belum tuntas,
     * sehingga barang tidak pernah masuk tanpa komitmen resmi (PLAN 19).
     */
    public function isReceivable(): bool
    {
        return in_array($this->status, [DocumentStatus::Approved, DocumentStatus::PartiallyProcessed], true);
    }

    public function isInvoiceable(): bool
    {
        return in_array(
            $this->status,
            [DocumentStatus::Approved, DocumentStatus::PartiallyProcessed, DocumentStatus::Completed],
            true,
        );
    }

    public function outstandingBaseQuantity(): float
    {
        return round(
            $this->items->sum(fn (PurchaseOrderItem $item) => $item->outstandingBaseQuantity()),
            4,
        );
    }

    public function isBackorder(): bool
    {
        return $this->status === DocumentStatus::PartiallyProcessed && $this->outstandingBaseQuantity() > 0;
    }

    public function approvalDocumentType(): string
    {
        return 'purchase_order';
    }

    public function approvalTitle(): string
    {
        return 'Purchase Order '.$this->number.' - '.($this->supplier?->name ?? '');
    }

    public function approvalAmount(): float
    {
        return (float) $this->total;
    }

    public function approvalBranchId(): ?int
    {
        return $this->branch_id;
    }

    public function approvalUrl(): ?string
    {
        return route('purchase.orders.detail', $this);
    }

    public function onApprovalApproved(ApprovalRequest $request): void
    {
        $this->status = DocumentStatus::Approved;
        $this->approved_by = $request->steps->last()?->acted_by;
        $this->approved_at = now();
        $this->save();
    }

    public function onApprovalRejected(ApprovalRequest $request): void
    {
        $this->status = DocumentStatus::Rejected;
        $this->rejection_reason = $request->steps->last()?->note;
        $this->save();
    }
}
