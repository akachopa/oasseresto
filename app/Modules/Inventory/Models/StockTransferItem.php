<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'base_quantity' => 'float',
            'received_base_quantity' => 'float',
            'unit_cost' => 'float',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function outstandingBaseQuantity(): float
    {
        return round($this->base_quantity - $this->received_base_quantity, 4);
    }
}
