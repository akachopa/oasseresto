<?php

declare(strict_types=1);

namespace App\Modules\Product\Models;

use App\Modules\Company\Models\Branch;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\PriceRuleType;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class PriceRule extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'type' => PriceRuleType::class,
            'min_quantity' => 'float',
            'max_quantity' => 'float',
            'price' => 'float',
            'discount_percent' => 'float',
            'discount_amount' => 'float',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function priceLevel(): BelongsTo
    {
        return $this->belongsTo(PriceLevel::class);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function scopeEffectiveOn(Builder $query, Carbon $date): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $date->toDateString()))
            ->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date->toDateString()));
    }

    /**
     * Hitung harga hasil aturan ini terhadap harga acuan.
     */
    public function applyTo(float $basePrice): float
    {
        return match ($this->mode) {
            'fixed' => (float) ($this->price ?? $basePrice),
            'discount_percent' => round($basePrice * (1 - ($this->discount_percent ?? 0) / 100), 4),
            'discount_amount' => max(0, round($basePrice - ($this->discount_amount ?? 0), 4)),
            default => $basePrice,
        };
    }

    /**
     * Skor spesifisitas: aturan yang lebih spesifik dipakai lebih dulu.
     */
    public function specificity(): int
    {
        return ($this->priority * 1000)
            + $this->type->priority()
            + ($this->customer_id ? 100 : 0)
            + ($this->customer_group_id ? 60 : 0)
            + ($this->product_id ? 40 : 0)
            + ($this->branch_id ? 20 : 0)
            + ($this->payment_term ? 10 : 0)
            + ($this->min_quantity > 0 ? 5 : 0);
    }
}
