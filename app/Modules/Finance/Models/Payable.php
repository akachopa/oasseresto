<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Company\Models\Branch;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\PayableStatus;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payable extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'status' => PayableStatus::class,
            'invoice_date' => 'date',
            'due_date' => 'date',
            'amount' => 'float',
            'paid_amount' => 'float',
            'outstanding_amount' => 'float',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [
            PayableStatus::Open,
            PayableStatus::PartiallyPaid,
            PayableStatus::Overdue,
        ]);
    }

    public function isOverdue(): bool
    {
        return $this->outstanding_amount > 0 && $this->due_date->isPast();
    }

    public function daysOverdue(): int
    {
        return $this->isOverdue() ? (int) $this->due_date->diffInDays(now()) : 0;
    }

    /**
     * Status dihitung dari sisa hutang, bukan disetel manual, supaya aging
     * dan pembayaran tidak pernah tidak sinkron (PLAN 35).
     */
    public function refreshStatus(): self
    {
        $this->outstanding_amount = round($this->amount - $this->paid_amount, 4);

        $this->status = match (true) {
            $this->outstanding_amount <= 0 => PayableStatus::Paid,
            $this->paid_amount > 0 && $this->isOverdue() => PayableStatus::Overdue,
            $this->paid_amount > 0 => PayableStatus::PartiallyPaid,
            $this->isOverdue() => PayableStatus::Overdue,
            default => PayableStatus::Open,
        };

        return $this;
    }
}
