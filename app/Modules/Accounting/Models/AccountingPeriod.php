<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use App\Models\User;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\AccountingPeriodStatus;
use App\Modules\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class AccountingPeriod extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'status' => AccountingPeriodStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'accounting_period_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function isOpen(): bool
    {
        return $this->status === AccountingPeriodStatus::Open;
    }

    public function allowsPosting(): bool
    {
        return $this->status->allowsPosting();
    }

    public function label(): string
    {
        return Carbon::create((int) $this->year, (int) $this->month, 1)->translatedFormat('F Y');
    }

    public function contains(Carbon $date): bool
    {
        return $date->betweenIncluded($this->start_date, $this->end_date);
    }
}
