<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnItem extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'base_quantity' => 'float',
            'unit_price' => 'float',
            'unit_cost' => 'float',
            'tax_amount' => 'float',
            'line_total' => 'float',
        ];
    }

    public function return(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class, 'purchase_return_id');
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

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }
}
