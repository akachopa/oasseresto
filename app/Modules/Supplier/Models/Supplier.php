<?php

declare(strict_types=1);

namespace App\Modules\Supplier\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Core\Models\TaxCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends BaseModel
{
    use BelongsToCompany, SoftDeletes;

    protected function casts(): array
    {
        return [
            'payment_term' => PaymentTermType::class,
            'minimum_order_amount' => 'float',
            'outstanding_amount' => 'float',
            'on_time_rate' => 'float',
            'quality_rate' => 'float',
            'average_lead_time' => 'float',
            'total_purchase' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(SupplierPriceHistory::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Skor performa gabungan untuk halaman perbandingan supplier (PLAN 21).
     */
    public function performanceScore(): float
    {
        return round(($this->on_time_rate * 0.6) + ($this->quality_rate * 0.4), 2);
    }

    public function performanceLabel(): string
    {
        return match (true) {
            $this->order_count === 0 => 'Belum ada riwayat',
            $this->performanceScore() >= 90 => 'Sangat baik',
            $this->performanceScore() >= 75 => 'Baik',
            $this->performanceScore() >= 60 => 'Cukup',
            default => 'Perlu perhatian',
        };
    }

    public function performanceColor(): string
    {
        return match (true) {
            $this->order_count === 0 => 'muted',
            $this->performanceScore() >= 75 => 'success',
            $this->performanceScore() >= 60 => 'warning',
            default => 'danger',
        };
    }

    public function getLabelAttribute(): string
    {
        return "{$this->code} - {$this->name}";
    }
}
