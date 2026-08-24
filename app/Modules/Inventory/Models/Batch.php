<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Product\Models\Product;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Batch extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'expiry_date' => 'date',
            'initial_quantity' => 'float',
            'quantity' => 'float',
            'unit_cost' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('quantity', '>', 0);
    }

    /**
     * Urutan FEFO: yang paling dekat kedaluwarsa keluar lebih dulu, batch
     * tanpa tanggal kedaluwarsa memakai urutan penerimaan (PLAN 26).
     */
    public function scopeFefo(Builder $query): Builder
    {
        return $query
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->orderBy('received_date')
            ->orderBy('id');
    }

    public function daysToExpiry(): ?int
    {
        return $this->expiry_date?->diffInDays(now()->startOfDay(), false) * -1;
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    public function isNearExpiry(?int $days = null): bool
    {
        $days ??= (int) config('oasse.inventory.near_expiry_days', 60);

        return $this->expiry_date !== null
            && ! $this->isExpired()
            && $this->expiry_date->lte(now()->addDays($days));
    }
}
