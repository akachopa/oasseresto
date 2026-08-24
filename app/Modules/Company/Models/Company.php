<?php

declare(strict_types=1);

namespace App\Modules\Company\Models;

use App\Models\User;
use App\Modules\Core\Enums\CostingMethod;
use App\Modules\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends BaseModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'fiscal_year_start' => 'date',
            'allow_negative_stock' => 'boolean',
            'is_active' => 'boolean',
            'costing_method' => CostingMethod::class,
        ];
    }

    public function businessUnits(): HasMany
    {
        return $this->hasMany(BusinessUnit::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }
}
