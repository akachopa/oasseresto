<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Auth\Support\PermissionRegistry;
use App\Modules\Auth\Support\RoleTemplate;
use App\Modules\Company\Models\Company;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionRegistry::all() as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Company::query()->each(function (Company $company): void {
            setPermissionsTeamId($company->id);

            foreach (array_keys(RoleTemplate::all()) as $name) {
                $role = Role::firstOrCreate([
                    'name' => $name,
                    'guard_name' => 'web',
                    'company_id' => $company->id,
                ]);

                $role->syncPermissions(RoleTemplate::resolve($name));
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
