<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\User;
use App\Modules\Approval\Contracts\Approvable;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Company\Models\Branch;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Core\Models\CostCenter;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends BaseModel implements Approvable
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'expense_date' => 'date',
            'amount' => 'float',
            'tax_amount' => 'float',
            'total' => 'float',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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

    public function isPayable(): bool
    {
        return $this->status === DocumentStatus::Approved;
    }

    public function isPosted(): bool
    {
        return $this->status === DocumentStatus::Posted;
    }

    public function approvalDocumentType(): string
    {
        return 'expense';
    }

    public function approvalTitle(): string
    {
        return 'Biaya '.$this->number.' - '.($this->category?->name ?? '');
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
        return route('finance.expenses.detail', $this);
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
