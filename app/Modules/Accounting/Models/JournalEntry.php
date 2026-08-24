<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use App\Models\User;
use App\Modules\Company\Models\Branch;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\JournalStatus;
use App\Modules\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'status' => JournalStatus::class,
            'entry_date' => 'date',
            'total_debit' => 'float',
            'total_credit' => 'float',
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('sequence');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversal(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', JournalStatus::Posted);
    }

    public function isPosted(): bool
    {
        return $this->status === JournalStatus::Posted;
    }

    public function isReversed(): bool
    {
        return $this->status === JournalStatus::Reversed;
    }

    /**
     * Jurnal yang sudah diposting tidak boleh diubah; koreksi dilakukan lewat
     * jurnal pembalik agar jejak audit tetap utuh (PLAN 52).
     */
    public function isImmutable(): bool
    {
        return $this->status !== JournalStatus::Draft;
    }

    public function isBalanced(): bool
    {
        return abs($this->total_debit - $this->total_credit) < 0.0001;
    }
}
