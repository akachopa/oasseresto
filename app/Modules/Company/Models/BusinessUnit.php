<?php

declare(strict_types=1);

namespace App\Modules\Company\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessUnit extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}
