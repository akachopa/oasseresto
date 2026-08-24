<?php

declare(strict_types=1);

namespace App\Modules\Customer\Models;

use App\Models\User;
use App\Modules\Company\Models\Branch;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Product\Models\PriceLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends BaseModel
{
    use BelongsToCompany, SoftDeletes;

    protected function casts(): array
    {
        return [
            'payment_term' => PaymentTermType::class,
            'credit_limit' => 'float',
            'outstanding_amount' => 'float',
            'overdue_amount' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function priceLevel(): BelongsTo
    {
        return $this->belongsTo(PriceLevel::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesman_id');
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function creditProfile(): HasOne
    {
        return $this->hasOne(CustomerCreditProfile::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Level harga efektif: milik customer, lalu grup, lalu default company.
     */
    public function effectivePriceLevelId(): ?int
    {
        return $this->price_level_id
            ?? $this->group?->price_level_id
            ?? PriceLevel::where('is_default', true)->value('id');
    }

    public function effectivePaymentTerm(): PaymentTermType
    {
        return $this->payment_term ?? $this->group?->default_payment_term ?? PaymentTermType::Cash;
    }

    public function availableCredit(): float
    {
        return round($this->credit_limit - $this->outstanding_amount, 2);
    }

    public function getLabelAttribute(): string
    {
        return "{$this->code} - {$this->name}";
    }
}
