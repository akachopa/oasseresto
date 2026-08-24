<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'base_quantity' => 'float',
            'unit_price' => 'float',
            'discount_percent' => 'float',
            'discount_amount' => 'float',
            'tax_amount' => 'float',
            'line_total' => 'float',
            'received_base_quantity' => 'float',
            'invoiced_base_quantity' => 'float',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'purchase_request_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    public function outstandingBaseQuantity(): float
    {
        return max(0, round($this->base_quantity - $this->received_base_quantity, 4));
    }

    public function uninvoicedBaseQuantity(): float
    {
        return max(0, round($this->received_base_quantity - $this->invoiced_base_quantity, 4));
    }

    /**
     * Harga per satuan dasar dipakai sebagai harga pokok saat penerimaan,
     * termasuk diskon baris supaya HPP mencerminkan harga bersih.
     */
    public function baseUnitCost(): float
    {
        if ($this->base_quantity <= 0) {
            return 0.0;
        }

        return round(($this->quantity * $this->unit_price - $this->discount_amount) / $this->base_quantity, 4);
    }
}
