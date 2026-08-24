<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\BusinessUnit;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\CostingMethod;
use App\Modules\Core\Models\CostCenter;
use App\Modules\Core\Models\TaxCode;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['code' => 'SGB'],
            [
                'name' => 'PT Sumber Grosir Bengkulu',
                'legal_name' => 'PT Sumber Grosir Bengkulu',
                'tax_number' => '01.234.567.8-901.000',
                'phone' => '0736-123456',
                'email' => 'admin@sumbergrosir.id',
                'address' => 'Jl. Suprapto No. 45',
                'city' => 'Bengkulu',
                'province' => 'Bengkulu',
                'currency' => 'IDR',
                'fiscal_year_start' => now()->startOfYear()->toDateString(),
                'costing_method' => CostingMethod::WeightedAverage,
                'allow_negative_stock' => false,
                'settings' => [
                    'credit_mode' => 'approval',
                    'min_margin_percent' => 5,
                ],
            ],
        );

        $unit = BusinessUnit::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'GROSIR'],
            ['name' => 'Unit Grosir & Distribusi'],
        );

        $branches = [
            ['code' => 'BKL', 'name' => 'Bengkulu', 'city' => 'Bengkulu', 'is_default' => true],
            ['code' => 'CRP', 'name' => 'Curup', 'city' => 'Curup', 'is_default' => false],
        ];

        foreach ($branches as $data) {
            $branch = Branch::firstOrCreate(
                ['company_id' => $company->id, 'code' => $data['code']],
                [
                    'business_unit_id' => $unit->id,
                    'name' => $data['name'],
                    'city' => $data['city'],
                    'is_default' => $data['is_default'],
                    'address' => 'Jl. Raya '.$data['name'],
                    'phone' => '0736-'.rand(100000, 999999),
                ],
            );

            Warehouse::firstOrCreate(
                ['company_id' => $company->id, 'code' => 'GU-'.$data['code']],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Gudang Utama '.$data['name'],
                    'type' => 'main',
                    'is_default' => $data['is_default'],
                ],
            );

            Warehouse::firstOrCreate(
                ['company_id' => $company->id, 'code' => 'GO-'.$data['code']],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Gudang Outlet '.$data['name'],
                    'type' => 'outlet',
                ],
            );

            CostCenter::firstOrCreate(
                ['company_id' => $company->id, 'code' => 'CC-'.$data['code']],
                ['branch_id' => $branch->id, 'name' => 'Cost Center '.$data['name']],
            );
        }

        $taxes = [
            ['code' => 'PPN11', 'name' => 'PPN 11%', 'rate' => 11, 'is_inclusive' => false],
            ['code' => 'PPN11I', 'name' => 'PPN 11% Termasuk', 'rate' => 11, 'is_inclusive' => true],
            ['code' => 'NONPPN', 'name' => 'Tanpa PPN', 'rate' => 0, 'is_inclusive' => false],
        ];

        foreach ($taxes as $tax) {
            TaxCode::firstOrCreate(
                ['company_id' => $company->id, 'code' => $tax['code']],
                $tax + ['for_sales' => true, 'for_purchase' => true],
            );
        }
    }
}
