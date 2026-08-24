<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Enums\PriceRuleType;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerCreditProfile;
use App\Modules\Customer\Models\CustomerGroup;
use App\Modules\Product\Models\Brand;
use App\Modules\Product\Models\PriceLevel;
use App\Modules\Product\Models\PriceRule;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductCategory;
use App\Modules\Product\Models\Unit;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Database\Seeder;

/**
 * Katalog realistis PT Sumber Grosir Bengkulu: 200+ SKU, 50 customer, 10 supplier.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'SGB')->firstOrFail();
        $bengkulu = Branch::where('company_id', $company->id)->where('code', 'BKL')->firstOrFail();
        $curup = Branch::where('company_id', $company->id)->where('code', 'CRP')->firstOrFail();
        $salesman = User::withoutGlobalScopes()->where('email', 'sales@oasse.id')->first();

        app(ScopeManager::class)->forCompany($company->id, function () use ($company, $bengkulu, $curup, $salesman): void {
            $this->categoriesAndBrands($company);
            $this->products($company);
            $this->customers($company, $bengkulu, $curup, $salesman);
            $this->suppliers($company);
        }, $bengkulu->id);
    }

    private function categoriesAndBrands(Company $company): void
    {
        foreach ([
            ['SEMBAKO', 'Sembako'],
            ['MINUMAN', 'Minuman'],
            ['MAKANAN', 'Makanan Instan'],
            ['PERAWATAN', 'Perawatan Tubuh'],
            ['RUMAH', 'Rumah Tangga'],
            ['SNACK', 'Snack & Permen'],
        ] as [$code, $name]) {
            ProductCategory::firstOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'is_active' => true],
            );
        }

        foreach ([
            ['SANIA', 'Sania'],
            ['TROPICAL', 'Tropical'],
            ['INDOFOOD', 'Indofood'],
            ['KAPALAPI', 'Kapal Api'],
            ['ULTRA', 'Ultra Milk'],
            ['LIFEBUOY', 'Lifebuoy'],
            ['RINSO', 'Rinso'],
            ['AQUA', 'Aqua'],
            ['CHITATO', 'Chitato'],
            ['GULAKU', 'Gulaku'],
            ['SEGITIGA', 'Segitiga Biru'],
            ['SARIWANGI', 'Sariwangi'],
        ] as [$code, $name]) {
            Brand::firstOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'is_active' => true],
            );
        }
    }

    private function products(Company $company): void
    {
        $categories = ProductCategory::where('company_id', $company->id)->pluck('id', 'code');
        $brands = Brand::where('company_id', $company->id)->pluck('id', 'code');
        $units = Unit::where('company_id', $company->id)->pluck('id', 'code');
        $levels = PriceLevel::where('company_id', $company->id)->pluck('id', 'code');
        $dusId = $units['DUS'] ?? null;

        $families = [
            ['BRS', 'Beras', 'SEMBAKO', 'SANIA', 'KG', 13_500, 16_500, false],
            ['MG', 'Minyak Goreng', 'SEMBAKO', 'TROPICAL', 'LTR', 14_000, 17_500, false],
            ['GLA', 'Gula Pasir', 'SEMBAKO', 'GULAKU', 'KG', 12_500, 15_000, false],
            ['TPG', 'Tepung Terigu', 'SEMBAKO', 'SEGITIGA', 'KG', 9_500, 12_000, false],
            ['MIE', 'Mie Instan', 'MAKANAN', 'INDOFOOD', 'PCS', 2_800, 3_500, false],
            ['KPI', 'Kopi Bubuk', 'MINUMAN', 'KAPALAPI', 'PCS', 8_500, 11_000, false],
            ['TEH', 'Teh Celup', 'MINUMAN', 'SARIWANGI', 'PCS', 6_000, 8_000, false],
            ['SSU', 'Susu UHT', 'MINUMAN', 'ULTRA', 'BTL', 5_500, 7_200, true],
            ['SBN', 'Sabun Mandi', 'PERAWATAN', 'LIFEBUOY', 'PCS', 3_200, 4_500, false],
            ['DTR', 'Deterjen', 'RUMAH', 'RINSO', 'PCS', 11_000, 14_500, false],
            ['AIR', 'Air Mineral', 'MINUMAN', 'AQUA', 'BTL', 2_200, 3_000, false],
            ['SNK', 'Keripik', 'SNACK', 'CHITATO', 'PCS', 7_500, 9_800, false],
        ];

        $mieProduct = null;

        foreach ($families as [$prefix, $name, $cat, $brand, $unitCode, $cost, $price, $batch]) {
            $unitId = $units[$unitCode];
            $pack = in_array($unitCode, ['PCS', 'BTL'], true);

            for ($n = 1; $n <= 20; $n++) {
                $sku = sprintf('%s-%02d', $prefix, $n);
                $variant = $name.' '.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
                $itemCost = $cost + ($n * 150);
                $itemPrice = $price + ($n * 200);

                $product = Product::firstOrCreate(
                    ['company_id' => $company->id, 'sku' => $sku],
                    [
                        'name' => $variant,
                        'product_category_id' => $categories[$cat],
                        'brand_id' => $brands[$brand],
                        'base_unit_id' => $unitId,
                        'purchase_unit_id' => $unitId,
                        'sales_unit_id' => $unitId,
                        'track_batch' => $batch,
                        'track_expiry' => $batch,
                        'is_stocked' => true,
                        'reorder_point' => $pack ? 24 : 10,
                        'minimum_stock' => $pack ? 12 : 5,
                        'last_purchase_cost' => $itemCost,
                        'average_cost' => $itemCost,
                        'base_price' => $itemPrice,
                        'is_active' => true,
                    ],
                );

                $product->units()->firstOrCreate(
                    ['unit_id' => $unitId],
                    ['conversion_to_base' => 1, 'is_base' => true],
                );

                if ($pack && $dusId && $n % 3 === 0) {
                    $product->units()->firstOrCreate(
                        ['unit_id' => $dusId],
                        ['conversion_to_base' => 12, 'is_base' => false],
                    );
                }

                foreach ([
                    'GROSIR' => 1.0,
                    'SEMI' => 1.06,
                    'RETAIL' => 1.14,
                ] as $level => $factor) {
                    if (! isset($levels[$level])) {
                        continue;
                    }

                    $product->prices()->firstOrCreate(
                        ['price_level_id' => $levels[$level], 'unit_id' => $unitId],
                        ['price' => round($itemPrice * $factor, 4), 'is_active' => true],
                    );
                }

                if ($prefix === 'MIE' && $n === 1) {
                    $mieProduct = $product;
                }
            }
        }

        if ($mieProduct !== null) {
            PriceRule::firstOrCreate(
                ['company_id' => $company->id, 'name' => 'Tier lusin mie instan'],
                [
                    'type' => PriceRuleType::QuantityTier,
                    'product_id' => $mieProduct->id,
                    'min_quantity' => 12,
                    'mode' => 'discount_percent',
                    'discount_percent' => 5,
                    'is_active' => true,
                ],
            );
        }
    }

    private function customers(Company $company, Branch $bengkulu, Branch $curup, ?User $salesman): void
    {
        $groups = CustomerGroup::where('company_id', $company->id)->pluck('id', 'code');
        $names = [
            'Toko Berkah Jaya', 'Toko Sumber Rezeki', 'Warung Bu Ani', 'Toko Maju Lancar',
            'Toko Sinar Pagi', 'Warung Pak Budi', 'Toko Amanah Grosir', 'CV Mitra Bengkulu',
            'Toko Harapan Baru', 'Warung Pojok Manis', 'Toko Lima Saudara', 'Toko Barokah',
            'Warung Bu Rina', 'Toko Sentosa', 'Toko Melati Indah', 'UD Karya Abadi',
            'Toko Gembira', 'Warung Pak Tono', 'Toko Nusantara', 'Toko Pelangi',
            'Warung Bu Sari', 'Toko Cahaya', 'Toko Bintang', 'CV Prima Curup',
            'Toko Sejahtera', 'Warung Pak Andi', 'Toko Family', 'Toko Permata',
            'Warung Bu Lina', 'Toko Makmur Jaya', 'Toko Dua Putra', 'UD Surya Kencana',
            'Toko Indah Sari', 'Warung Pak Joko', 'Toko Rukun', 'Toko Purnama',
            'Warung Bu Tati', 'Toko Mega Grosir', 'Toko Kencana', 'CV Anugrah Curup',
            'Toko Sederhana', 'Warung Pak Udin', 'Toko Wijaya', 'Toko Delima',
            'Warung Bu Nia', 'Toko Abadi Jaya', 'Toko Tiga Berlian', 'Instansi Dinas PU',
            'Koperasi Karyawan SGB', 'Toko Umum Pasar Minggu',
        ];

        $groupCycle = ['TOKO', 'TOKO', 'WARUNG', 'UMUM', 'INSTANSI'];
        $cities = ['Bengkulu', 'Bengkulu', 'Curup', 'Manna', 'Argamakmur'];
        $terms = [
            'TOKO' => PaymentTermType::Net14,
            'WARUNG' => PaymentTermType::Net7,
            'UMUM' => PaymentTermType::Cash,
            'INSTANSI' => PaymentTermType::Net30,
        ];
        $limits = [
            'TOKO' => 25_000_000,
            'WARUNG' => 5_000_000,
            'UMUM' => 0,
            'INSTANSI' => 80_000_000,
        ];

        foreach ($names as $index => $name) {
            $code = sprintf('CUS-%02d', $index + 1);
            $group = $groupCycle[$index % count($groupCycle)];
            $city = $cities[$index % count($cities)];
            $branch = $city === 'Curup' ? $curup : $bengkulu;

            $customer = Customer::firstOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                [
                    'name' => $name,
                    'customer_group_id' => $groups[$group] ?? null,
                    'branch_id' => $branch->id,
                    'salesman_id' => in_array($group, ['TOKO', 'WARUNG'], true) ? $salesman?->id : null,
                    'phone' => '0813'.str_pad((string) (1000000 + $index), 7, '0', STR_PAD_LEFT),
                    'city' => $city,
                    'address' => 'Jl. Raya '.$city.' No. '.($index + 1),
                    'type' => $group === 'UMUM' ? 'retail' : 'wholesale',
                    'payment_term' => $terms[$group]->value,
                    'credit_limit' => $limits[$group],
                    'is_active' => true,
                ],
            );

            CustomerCreditProfile::firstOrCreate(
                ['customer_id' => $customer->id],
                ['company_id' => $company->id, 'payment_behavior' => 'unknown'],
            );
        }
    }

    private function suppliers(Company $company): void
    {
        $rows = [
            ['SUP-01', 'PT Indofood Sukses Makmur', 'Jakarta', 3],
            ['SUP-02', 'PT Wings Surya', 'Surabaya', 4],
            ['SUP-03', 'PT Unilever Indonesia', 'Jakarta', 5],
            ['SUP-04', 'PT Mayora Indah', 'Tangerang', 4],
            ['SUP-05', 'PT Sayap Mas Utama', 'Jakarta', 5],
            ['SUP-06', 'CV Sumber Pangan Bengkulu', 'Bengkulu', 2],
            ['SUP-07', 'PT Sinar Mas Agro', 'Medan', 7],
            ['SUP-08', 'UD Tiga Putra Grosir', 'Palembang', 3],
            ['SUP-09', 'PT Coca-Cola Distribution', 'Padang', 6],
            ['SUP-10', 'CV Aneka Sembako Curup', 'Curup', 2],
        ];

        foreach ($rows as [$code, $name, $city, $lead]) {
            Supplier::firstOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                [
                    'name' => $name,
                    'city' => $city,
                    'phone' => '0736-'.(100000 + (int) substr($code, -2)),
                    'address' => 'Kawasan Industri '.$city,
                    'payment_term' => PaymentTermType::Net30->value,
                    'lead_time_days' => $lead,
                    'is_active' => true,
                ],
            );
        }
    }
}
