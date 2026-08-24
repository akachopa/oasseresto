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
use App\Modules\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequest extends BaseModel implements Approvable
{
    use BelongsToCompany;

    /**
     * @return array<string, string>
     */
    public static function priorities(): array
    {
        return [
            'low' => 'Rendah',
            'normal' => 'Normal',
            'high' => 'Tinggi',
            'urgent' => 'Mendesak',
        ];
    }

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'request_date' => 'date',
            'needed_date' => 'date',
            'estimated_total' => 'float',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function priorityLabel(): string
    {
        return self::priorities()[$this->priority] ?? $this->priority;
    }

    /**
     * PR yang sudah disetujui boleh dijadikan PO sampai seluruh barangnya
     * terpesan; sisanya tetap terbuka agar bisa dipesan ke supplier lain.
     */
    public function isOrderable(): bool
    {
        return in_array($this->status, [DocumentStatus::Approved, DocumentStatus::PartiallyProcessed], true);
    }

    public function outstandingBaseQuantity(): float
    {
        return round(
            $this->items->sum(fn (PurchaseRequestItem $item) => $item->outstandingBaseQuantity()),
            4,
        );
    }

    public function approvalDocumentType(): string
    {
        return 'purchase_request';
    }

    public function approvalTitle(): string
    {
        return 'Purchase Request '.$this->number;
    }

    public function approvalAmount(): float
    {
        return (float) $this->estimated_total;
    }

    public function approvalBranchId(): ?int
    {
        return $this->branch_id;
    }

    public function approvalUrl(): ?string
    {
        return route('purchase.requests.detail', $this);
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
