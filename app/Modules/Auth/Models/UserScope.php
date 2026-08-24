<?php

declare(strict_types=1);

namespace App\Modules\Auth\Models;

use App\Models\User;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\ScopeLevel;
use App\Modules\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserScope extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return ['level' => ScopeLevel::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function describe(): string
    {
        return match ($this->level) {
            ScopeLevel::Company => 'Seluruh Company',
            ScopeLevel::Branch => 'Cabang: '.($this->branch?->name ?? '-'),
            ScopeLevel::Warehouse => 'Gudang: '.($this->warehouse?->name ?? '-'),
        };
    }
}
