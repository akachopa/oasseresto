<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Auth\Models\UserScope;
use App\Modules\Auth\Support\RoleTemplate;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\ScopeLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'SGB')->firstOrFail();
        setPermissionsTeamId($company->id);

        $bengkulu = Branch::where('company_id', $company->id)->where('code', 'BKL')->firstOrFail();
        $curup = Branch::where('company_id', $company->id)->where('code', 'CRP')->firstOrFail();
        $gudangBengkulu = Warehouse::where('company_id', $company->id)->where('code', 'GU-BKL')->firstOrFail();

        $users = [
            ['name' => 'Hendra Wijaya', 'email' => 'owner@oasse.id', 'username' => 'owner', 'role' => 'Owner', 'job' => 'Owner'],
            ['name' => 'Rina Kusuma', 'email' => 'gm@oasse.id', 'username' => 'gm', 'role' => 'General Manager', 'job' => 'General Manager'],
            ['name' => 'Bagus Saputra', 'email' => 'bm.curup@oasse.id', 'username' => 'bmcurup', 'role' => 'Branch Manager', 'job' => 'Branch Manager Curup', 'branch' => $curup],
            ['name' => 'Andi Pratama', 'email' => 'purchasing@oasse.id', 'username' => 'purchasing', 'role' => 'Purchasing', 'job' => 'Staff Purchasing'],
            ['name' => 'Yanto Susilo', 'email' => 'gudang@oasse.id', 'username' => 'gudang', 'role' => 'Warehouse Staff', 'job' => 'Staff Gudang', 'warehouse' => $gudangBengkulu],
            ['name' => 'Dewi Anggraini', 'email' => 'spv.gudang@oasse.id', 'username' => 'spvgudang', 'role' => 'Warehouse Supervisor', 'job' => 'Supervisor Gudang', 'branch' => $bengkulu],
            ['name' => 'Rizky Ramadhan', 'email' => 'sales@oasse.id', 'username' => 'sales', 'role' => 'Salesman', 'job' => 'Salesman', 'branch' => $bengkulu],
            ['name' => 'Tono Hartanto', 'email' => 'spv.sales@oasse.id', 'username' => 'spvsales', 'role' => 'Sales Supervisor', 'job' => 'Supervisor Sales', 'branch' => $bengkulu],
            ['name' => 'Sari Melati', 'email' => 'finance@oasse.id', 'username' => 'finance', 'role' => 'Finance', 'job' => 'Staff Finance'],
            ['name' => 'Budi Santoso', 'email' => 'accounting@oasse.id', 'username' => 'accounting', 'role' => 'Accounting', 'job' => 'Staff Accounting'],
            ['name' => 'Lia Permata', 'email' => 'kasir@oasse.id', 'username' => 'kasir', 'role' => 'Cashier', 'job' => 'Kasir', 'branch' => $bengkulu],
        ];

        foreach ($users as $data) {
            $user = User::withoutGlobalScopes()->firstOrCreate(
                ['email' => $data['email']],
                [
                    'company_id' => $company->id,
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'job_title' => $data['job'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'default_branch_id' => ($data['branch'] ?? $bengkulu)->id,
                    'is_active' => true,
                ],
            );

            $user->syncRoles([$data['role']]);

            UserScope::where('user_id', $user->id)->delete();

            if (isset($data['warehouse'])) {
                UserScope::create([
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                    'level' => ScopeLevel::Warehouse,
                    'branch_id' => $data['warehouse']->branch_id,
                    'warehouse_id' => $data['warehouse']->id,
                ]);

                continue;
            }

            if (isset($data['branch']) && ! in_array($data['role'], RoleTemplate::companyWide(), true)) {
                UserScope::create([
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                    'level' => ScopeLevel::Branch,
                    'branch_id' => $data['branch']->id,
                ]);

                continue;
            }

            UserScope::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'level' => ScopeLevel::Company,
            ]);
        }
    }
}
