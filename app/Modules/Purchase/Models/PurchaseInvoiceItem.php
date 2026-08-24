<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseInvoiceItem extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'base_quantity' => 'float',
            'unit_price' => 'float',
            'discount_amount' => 'float',
            'tax_amount' => 'float',
            'line_total' => 'float',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function receiptItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class, 'goods_receipt_item_id');
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

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }
}
