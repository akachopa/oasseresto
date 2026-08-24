<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Accounting\Models\Account;
use App\Modules\Company\Models\Branch;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashAccount extends BaseModel
{
    use BelongsToCompany;

    /**
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            'cash' => 'Kas',
            'bank' => 'Bank',
            'petty' => 'Kas Kecil',
        ];
    }

    protected function casts(): array
    {
        return [
            'opening_balance' => 'float',
            'balance' => 'float',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function typeLabel(): string
    {
        return self::types()[$this->type] ?? $this->type;
    }

    public function isBank(): bool
    {
        return $this->type === 'bank';
    }

    public function getLabelAttribute(): string
    {
        return $this->isBank() && $this->account_number
            ? "{$this->name} ({$this->account_number})"
            : (string) $this->name;
    }
}
