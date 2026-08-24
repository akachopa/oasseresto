<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Auth\Models\UserScope;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Core\Enums\ScopeLevel;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function defaultBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'default_branch_id');
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(UserScope::class);
    }

    /**
     * Company scope berarti user melihat seluruh cabang dan gudang.
     */
    public function hasCompanyWideScope(): bool
    {
        return $this->scopes->isEmpty()
            || $this->scopes->contains(fn (UserScope $scope) => $scope->level === ScopeLevel::Company);
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        return strtoupper(mb_substr($parts[0] ?? 'U', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    }

    /**
     * Home dashboard berbeda per role (PLAN 13).
     */
    public function homeRoute(): string
    {
        return match (true) {
            $this->hasAnyRole(['Owner', 'General Manager', 'Branch Manager', 'Auditor', 'Viewer']) => 'dashboard',
            $this->hasRole('Cashier') => 'pos.index',
            $this->hasAnyRole(['Warehouse Staff', 'Warehouse Supervisor']) => 'home.warehouse',
            $this->hasRole('Purchasing') => 'home.purchasing',
            $this->hasAnyRole(['Salesman', 'Sales Supervisor']) => 'home.salesman',
            $this->hasRole('Finance') => 'home.finance',
            $this->hasRole('Accounting') => 'home.accounting',
            default => 'dashboard',
        };
    }
}
