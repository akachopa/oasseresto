<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\User;
use App\Modules\Company\Models\Branch;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\ReceivableStatus;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receivable extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'status' => ReceivableStatus::class,
            'invoice_date' => 'date',
            'due_date' => 'date',
            'amount' => 'float',
            'paid_amount' => 'float',
            'outstanding_amount' => 'float',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesman_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ReceivableStatus::Open,
            ReceivableStatus::PartiallyPaid,
            ReceivableStatus::Overdue,
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
     * Umur piutang mengikuti bucket yang dikonfigurasi company (PLAN 33).
     */
    public function agingBucket(): string
    {
        $days = $this->daysOverdue();
        $buckets = (array) config('oasse.credit.aging_buckets', [30, 60, 90]);

        if ($days <= 0) {
            return 'belum jatuh tempo';
        }

        foreach ($buckets as $bucket) {
            if ($days <= $bucket) {
                return '1-'.$bucket.' hari';
            }
        }

        return '> '.end($buckets).' hari';
    }

    /**
     * Status dihitung dari sisa piutang, bukan disetel manual, supaya aging
     * dan penerimaan pembayaran tidak pernah tidak sinkron (PLAN 33).
     */
    public function refreshStatus(): self
    {
        $this->outstanding_amount = round($this->amount - $this->paid_amount, 4);

        $this->status = match (true) {
            $this->outstanding_amount <= 0 => ReceivableStatus::Paid,
            $this->isOverdue() => ReceivableStatus::Overdue,
            $this->paid_amount > 0 => ReceivableStatus::PartiallyPaid,
            default => ReceivableStatus::Open,
        };

        return $this;
    }
}
