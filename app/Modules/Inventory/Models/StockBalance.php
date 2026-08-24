<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Product\Models\Product;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'reserved_quantity' => 'float',
            'average_cost' => 'float',
            'total_value' => 'float',
            'last_movement_at' => 'datetime',
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

    /**
     * Stok yang benar-benar bisa dijanjikan ke customer: saldo dikurangi
     * bagian yang sudah dialokasikan ke order lain (PLAN 27).
     */
    public function availableQuantity(): float
    {
        return round($this->quantity - $this->reserved_quantity, 4);
    }
}
