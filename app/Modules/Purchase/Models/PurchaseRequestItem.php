<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'base_quantity' => 'float',
            'ordered_base_quantity' => 'float',
            'estimated_price' => 'float',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function suggestedSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'suggested_supplier_id');
    }

    public function outstandingBaseQuantity(): float
    {
        return max(0, round($this->base_quantity - $this->ordered_base_quantity, 4));
    }

    public function estimatedTotal(): float
    {
        return round($this->quantity * $this->estimated_price, 4);
    }
}
