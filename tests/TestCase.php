<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use App\Modules\Auth\Models\UserScope;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\ScopeLevel;
use App\Modules\Core\Services\ScopeManager;
use Database\Seeders\AccountSeeder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        app(ScopeManager::class)->reset();

        parent::tearDown();
    }

    /**
     * Bangun satu company lengkap dengan cabang, gudang, COA, dan role.
     *
     * @return array{company: Company, branch: Branch, warehouse: Warehouse}
     */
    protected function bootCompany(): array
    {
        app(ScopeManager::class)->withoutRestriction(function (): void {
            $this->seed(CompanySeeder::class);
            $this->seed(RolePermissionSeeder::class);
            $this->seed(AccountSeeder::class);
        });

        $company = Company::withoutGlobalScopes()->where('code', 'SGB')->firstOrFail();
        $branch = Branch::withoutGlobalScopes()->where('company_id', $company->id)->where('code', 'BKL')->firstOrFail();
        $warehouse = Warehouse::withoutGlobalScopes()->where('company_id', $company->id)->where('code', 'GU-BKL')->firstOrFail();

        app(ScopeManager::class)->setCompanyId($company->id)->setBranchId($branch->id);
        setPermissionsTeamId($company->id);

        return compact('company', 'branch', 'warehouse');
    }

    protected function makeUser(Company $company, string $role, ?Branch $branch = null): User
    {
        setPermissionsTeamId($company->id);

        $user = User::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => $role.' Test',
            'username' => str()->random(10),
            'email' => str()->random(10).'@oasse.test',
            'password' => Hash::make('password'),
            'default_branch_id' => $branch?->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->syncRoles([$role]);

        UserScope::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'level' => $branch ? ScopeLevel::Branch : ScopeLevel::Company,
            'branch_id' => $branch?->id,
        ]);

        return $user->fresh();
    }
}
