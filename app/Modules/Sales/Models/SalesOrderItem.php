<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderItem extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'base_quantity' => 'float',
            'list_price' => 'float',
            'unit_price' => 'float',
            'unit_cost' => 'float',
            'discount_percent' => 'float',
            'discount_amount' => 'float',
            'tax_amount' => 'float',
            'line_total' => 'float',
            'delivered_base_quantity' => 'float',
            'invoiced_base_quantity' => 'float',
            'reserved_base_quantity' => 'float',
            'margin_percent' => 'float',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(QuotationItem::class, 'quotation_item_id');
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
        return max(0, round($this->base_quantity - $this->delivered_base_quantity, 4));
    }

    public function uninvoicedBaseQuantity(): float
    {
        return max(0, round($this->delivered_base_quantity - $this->invoiced_base_quantity, 4));
    }

    /**
     * Harga per satuan dasar dipakai saat membuat baris pengiriman dan
     * invoice, termasuk diskon baris supaya nilainya konsisten.
     */
    public function baseUnitPrice(): float
    {
        if ($this->base_quantity <= 0) {
            return 0.0;
        }

        return round(($this->quantity * $this->unit_price - $this->discount_amount) / $this->base_quantity, 4);
    }
}
