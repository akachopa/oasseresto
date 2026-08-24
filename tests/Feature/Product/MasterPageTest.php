<?php

declare(strict_types=1);

use App\Modules\Customer\Models\Customer;
use App\Modules\Product\Models\PriceLevel;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use App\Modules\Supplier\Models\Supplier;

it('membuka seluruh halaman master data', function (): void {
    ['company' => $company, 'branch' => $branch] = $this->bootCompany();
    $owner = $this->makeUser($company, 'Owner', $branch);

    $product = Product::create([
        'company_id' => $company->id,
        'sku' => 'SMK-001',
        'name' => 'Produk Smoke Test',
        'base_unit_id' => Unit::where('code', 'PCS')->value('id'),
        'base_price' => 10_000,
    ]);

    $product->units()->create([
        'company_id' => $company->id,
        'unit_id' => $product->base_unit_id,
        'conversion_to_base' => 1,
        'is_base' => true,
    ]);

    $customer = Customer::create([
        'company_id' => $company->id,
        'code' => 'CUS00001',
        'name' => 'Toko Smoke',
    ]);

    $supplier = Supplier::create([
        'company_id' => $company->id,
        'code' => 'SUP0001',
        'name' => 'PT Smoke Supply',
    ]);

    $level = PriceLevel::where('code', 'GROSIR')->firstOrFail();

    $pages = [
        route('products.index'),
        route('products.create'),
        route('products.edit', $product),
        route('products.detail', $product),
        route('product-categories.index'),
        route('brands.index'),
        route('units.index'),
        route('price-levels.index'),
        route('price-levels.edit', $level),
        route('price-rules.index'),
        route('price-rules.create'),
        route('customers.index'),
        route('customers.create'),
        route('customers.edit', $customer),
        route('customers.detail', $customer),
        route('customer-groups.index'),
        route('suppliers.index'),
        route('suppliers.create'),
        route('suppliers.edit', $supplier),
        route('suppliers.detail', $supplier),
        route('suppliers.performance'),
    ];

    foreach ($pages as $page) {
        $this->actingAs($owner)->get($page)->assertOk();
    }
});

it('menyajikan data DataTables server-side untuk produk', function (): void {
    ['company' => $company, 'branch' => $branch] = $this->bootCompany();
    $owner = $this->makeUser($company, 'Owner', $branch);

    Product::create([
        'company_id' => $company->id,
        'sku' => 'TBL-001',
        'name' => 'Produk Tabel',
        'base_unit_id' => Unit::where('code', 'PCS')->value('id'),
        'base_price' => 5_000,
    ]);

    $this->actingAs($owner)
        ->post(route('products.data'), ['draw' => 1, 'start' => 0, 'length' => 10])
        ->assertOk()
        ->assertJsonPath('recordsTotal', 1)
        ->assertJsonPath('draw', 1);
});
