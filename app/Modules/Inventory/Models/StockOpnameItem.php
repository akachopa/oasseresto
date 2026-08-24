<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Product\Models\Product;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'system_base_quantity' => 'float',
            'counted_base_quantity' => 'float',
            'difference_base_quantity' => 'float',
            'unit_cost' => 'float',
            'difference_value' => 'float',
            'is_counted' => 'boolean',
        ];
    }

    public function opname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
