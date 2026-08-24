<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use App\Modules\Company\Models\Branch;
use App\Modules\Core\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostCenter extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
