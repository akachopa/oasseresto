<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Services\ScopeManager;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(ScopeManager::class)->withoutRestriction(function (): void {
            $this->call([
                CompanySeeder::class,
                RolePermissionSeeder::class,
                UserSeeder::class,
                AccountSeeder::class,
                MasterDataSeeder::class,
            ]);
        });
    }
}
