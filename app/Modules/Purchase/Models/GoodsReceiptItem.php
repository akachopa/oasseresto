<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'quantity' => 'float',
            'base_quantity' => 'float',
            'rejected_base_quantity' => 'float',
            'unit_cost' => 'float',
            'total_cost' => 'float',
            'invoiced_base_quantity' => 'float',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
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
     * Barang ditolak tidak masuk stok, jadi hanya sisanya yang diterima.
     */
    public function acceptedBaseQuantity(): float
    {
        return max(0, round($this->base_quantity - $this->rejected_base_quantity, 4));
    }

    public function uninvoicedBaseQuantity(): float
    {
        return max(0, round($this->acceptedBaseQuantity() - $this->invoiced_base_quantity, 4));
    }
}
