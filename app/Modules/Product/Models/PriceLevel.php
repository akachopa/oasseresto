<?php

declare(strict_types=1);

namespace App\Modules\Product\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;

class PriceLevel extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
