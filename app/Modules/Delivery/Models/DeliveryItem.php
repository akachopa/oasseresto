<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use App\Modules\Sales\Models\SalesOrderItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryItem extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'base_quantity' => 'float',
            'picked_base_quantity' => 'float',
            'unit_cost' => 'float',
            'total_cost' => 'float',
            'invoiced_base_quantity' => 'float',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id');
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

    /**
     * Yang benar-benar dikirim adalah yang berhasil dipicking, bukan yang
     * direncanakan, sehingga kekurangan barang terlihat apa adanya.
     */
    public function shippedBaseQuantity(): float
    {
        return round($this->picked_base_quantity, 4);
    }

    public function uninvoicedBaseQuantity(): float
    {
        return max(0, round($this->shippedBaseQuantity() - $this->invoiced_base_quantity, 4));
    }

    public function isShort(): bool
    {
        return round($this->picked_base_quantity, 4) < round($this->base_quantity, 4);
    }
}
