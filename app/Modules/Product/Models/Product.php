<?php

declare(strict_types=1);

namespace App\Modules\Product\Models;

use App\Modules\Accounting\Models\Account;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Core\Models\TaxCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

class Product extends BaseModel
{
    use BelongsToCompany, SoftDeletes;

    protected function casts(): array
    {
        return [
            'track_batch' => 'boolean',
            'track_expiry' => 'boolean',
            'track_serial' => 'boolean',
            'is_stocked' => 'boolean',
            'is_active' => 'boolean',
            'minimum_stock' => 'float',
            'maximum_stock' => 'float',
            'reorder_point' => 'float',
            'safety_stock' => 'float',
            'last_purchase_cost' => 'float',
            'average_cost' => 'float',
            'base_price' => 'float',
            'min_margin_percent' => 'float',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id');
    }

    public function salesUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'sales_unit_id');
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'inventory_account_id');
    }

    public function cogsAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cogs_account_id');
    }

    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'revenue_account_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function priceRules(): HasMany
    {
        return $this->hasMany(PriceRule::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeStocked(Builder $query): Builder
    {
        return $query->where('is_stocked', true);
    }

    /**
     * Faktor konversi satuan ke base unit. Seluruh kalkulasi stok dan HPP
     * memakai base unit (PLAN 14).
     */
    public function conversionFor(int $unitId): float
    {
        if ($unitId === (int) $this->base_unit_id) {
            return 1.0;
        }

        $conversion = $this->units->firstWhere('unit_id', $unitId)?->conversion_to_base
            ?? ProductUnit::where('product_id', $this->getKey())->where('unit_id', $unitId)->value('conversion_to_base');

        if ($conversion === null) {
            throw new RuntimeException("Satuan {$unitId} belum dikonfigurasi untuk produk {$this->sku}.");
        }

        return (float) $conversion;
    }

    public function toBaseQuantity(float $quantity, ?int $unitId = null): float
    {
        return round($quantity * $this->conversionFor($unitId ?? (int) $this->base_unit_id), 6);
    }

    public function fromBaseQuantity(float $baseQuantity, ?int $unitId = null): float
    {
        return round($baseQuantity / $this->conversionFor($unitId ?? (int) $this->base_unit_id), 6);
    }

    public function minimumMargin(): float
    {
        return $this->min_margin_percent
            ?? (float) ($this->company?->setting('min_margin_percent') ?? config('oasse.pricing.min_margin_percent'));
    }

    public function getLabelAttribute(): string
    {
        return "{$this->sku} - {$this->name}";
    }
}
