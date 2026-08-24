<?php

declare(strict_types=1);

namespace App\Modules\Product\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnit extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'conversion_to_base' => 'float',
            'is_base' => 'boolean',
            'allow_purchase' => 'boolean',
            'allow_sales' => 'boolean',
        ];
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
