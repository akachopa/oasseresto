<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Accounting\Models\Account;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
            'approval_threshold' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Biaya kecil di bawah ambang tidak perlu approval supaya operasional
     * harian tidak terhambat (PLAN 36).
     */
    public function needsApproval(float $amount): bool
    {
        return $this->requires_approval && $amount >= $this->approval_threshold;
    }

    public function getLabelAttribute(): string
    {
        return "{$this->code} - {$this->name}";
    }
}
