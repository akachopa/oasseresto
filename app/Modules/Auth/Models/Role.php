<?php

declare(strict_types=1);

namespace App\Modules\Auth\Models;

use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
