<?php

declare(strict_types=1);

namespace App\Modules\Supplier\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPriceHistory extends BaseModel
{
    use BelongsToCompany;

    protected $table = 'supplier_price_history';

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'price' => 'float',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
