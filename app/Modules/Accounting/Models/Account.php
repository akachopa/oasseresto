<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\AccountType;
use App\Modules\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'is_postable' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function scopePostable(Builder $query): Builder
    {
        return $query->where('is_postable', true)->where('is_active', true);
    }

    public function getLabelAttribute(): string
    {
        return "{$this->code} - {$this->name}";
    }

    protected static function booted(): void
    {
        static::saving(function (Account $account): void {
            if ($account->slug === '') {
                $account->slug = null;
            }
        });
    }
}
