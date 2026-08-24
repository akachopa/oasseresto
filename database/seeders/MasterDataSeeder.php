<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Company\Models\Company;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Customer\Models\CustomerGroup;
use App\Modules\Product\Models\PriceLevel;
use App\Modules\Product\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Master data minimum yang harus ada sebelum produk bisa dibuat: satuan,
 * level harga, dan grup customer. Isi katalog realistis ada di seeder demo.
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Company::all() as $company) {
            $this->units($company);
            $this->priceLevels($company);
            $this->customerGroups($company);
        }
    }

    private function units(Company $company): void
    {
        $units = [
            ['code' => 'PCS', 'name' => 'Pieces', 'category' => 'count'],
            ['code' => 'LSN', 'name' => 'Lusin', 'category' => 'count'],
            ['code' => 'DUS', 'name' => 'Dus', 'category' => 'count'],
            ['code' => 'PAK', 'name' => 'Pak', 'category' => 'count'],
            ['code' => 'BAL', 'name' => 'Bal', 'category' => 'count'],
            ['code' => 'KG', 'name' => 'Kilogram', 'category' => 'weight'],
            ['code' => 'SAK', 'name' => 'Sak', 'category' => 'weight'],
            ['code' => 'LTR', 'name' => 'Liter', 'category' => 'volume'],
            ['code' => 'BTL', 'name' => 'Botol', 'category' => 'volume'],
            ['code' => 'MTR', 'name' => 'Meter', 'category' => 'length'],
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(['company_id' => $company->id, 'code' => $unit['code']], $unit);
        }
    }

    private function priceLevels(Company $company): void
    {
        $levels = [
            ['code' => 'GROSIR', 'name' => 'Grosir', 'sequence' => 10, 'is_default' => true],
            ['code' => 'SEMI', 'name' => 'Semi Grosir', 'sequence' => 20, 'is_default' => false],
            ['code' => 'RETAIL', 'name' => 'Retail', 'sequence' => 30, 'is_default' => false],
            ['code' => 'PROYEK', 'name' => 'Proyek', 'sequence' => 40, 'is_default' => false],
        ];

        foreach ($levels as $level) {
            PriceLevel::firstOrCreate(['company_id' => $company->id, 'code' => $level['code']], $level);
        }
    }

    private function customerGroups(Company $company): void
    {
        $levels = PriceLevel::where('company_id', $company->id)->pluck('id', 'code');

        $groups = [
            [
                'code' => 'TOKO',
                'name' => 'Toko / Reseller',
                'price_level_id' => $levels['GROSIR'] ?? null,
                'default_payment_term' => PaymentTermType::Net14->value,
                'default_credit_limit' => 25_000_000,
            ],
            [
                'code' => 'WARUNG',
                'name' => 'Warung',
                'price_level_id' => $levels['SEMI'] ?? null,
                'default_payment_term' => PaymentTermType::Net7->value,
                'default_credit_limit' => 5_000_000,
            ],
            [
                'code' => 'UMUM',
                'name' => 'Pelanggan Umum',
                'price_level_id' => $levels['RETAIL'] ?? null,
                'default_payment_term' => PaymentTermType::Cash->value,
                'default_credit_limit' => 0,
            ],
            [
                'code' => 'INSTANSI',
                'name' => 'Instansi & Proyek',
                'price_level_id' => $levels['PROYEK'] ?? null,
                'default_payment_term' => PaymentTermType::Net30->value,
                'default_credit_limit' => 100_000_000,
            ],
        ];

        foreach ($groups as $group) {
            CustomerGroup::firstOrCreate(['company_id' => $company->id, 'code' => $group['code']], $group);
        }
    }
}
