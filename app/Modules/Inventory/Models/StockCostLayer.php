<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Product\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCostLayer extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'quantity' => 'float',
            'remaining_quantity' => 'float',
            'unit_cost' => 'float',
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

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('remaining_quantity', '>', 0);
    }

    public function scopeOldestFirst(Builder $query): Builder
    {
        return $query->orderBy('received_at')->orderBy('id');
    }
}
