<?php

declare(strict_types=1);

use App\Modules\Core\Enums\PriceRuleType;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerGroup;
use App\Modules\Product\Livewire\ProductForm;
use App\Modules\Product\Models\PriceLevel;
use App\Modules\Product\Models\PriceRule;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductPrice;
use App\Modules\Product\Models\Unit;
use App\Modules\Product\Services\MarginGuard;
use App\Modules\Product\Services\PricingService;
use Livewire\Livewire;

/**
 * @return array{product: Product, pcs: Unit, dus: Unit}
 */
function seedProduct(int $companyId): array
{
    $pcs = Unit::where('code', 'PCS')->firstOrFail();
    $dus = Unit::where('code', 'DUS')->firstOrFail();

    $product = Product::create([
        'company_id' => $companyId,
        'sku' => 'MG-001',
        'name' => 'Minyak Goreng 1L',
        'base_unit_id' => $pcs->id,
        'base_price' => 18_000,
        'average_cost' => 15_000,
    ]);

    $product->units()->create([
        'company_id' => $companyId,
        'unit_id' => $pcs->id,
        'conversion_to_base' => 1,
        'is_base' => true,
    ]);

    $product->units()->create([
        'company_id' => $companyId,
        'unit_id' => $dus->id,
        'conversion_to_base' => 12,
        'is_base' => false,
    ]);

    return ['product' => $product, 'pcs' => $pcs, 'dus' => $dus];
}

it('mengkonversi kuantitas satuan turunan ke satuan dasar', function (): void {
    ['company' => $company] = $this->bootCompany();
    ['product' => $product, 'dus' => $dus] = seedProduct($company->id);

    expect($product->toBaseQuantity(3, $dus->id))->toBe(36.0);
    expect($product->fromBaseQuantity(36, $dus->id))->toBe(3.0);
});

it('memakai harga level customer sebelum harga dasar produk', function (): void {
    ['company' => $company] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = seedProduct($company->id);

    $level = PriceLevel::where('code', 'RETAIL')->firstOrFail();

    ProductPrice::create([
        'company_id' => $company->id,
        'product_id' => $product->id,
        'price_level_id' => $level->id,
        'unit_id' => $pcs->id,
        'price' => 20_000,
    ]);

    $group = CustomerGroup::where('code', 'UMUM')->firstOrFail();
    $group->update(['price_level_id' => $level->id]);

    $customer = Customer::create([
        'company_id' => $company->id,
        'customer_group_id' => $group->id,
        'code' => 'CUS00001',
        'name' => 'Warung Bu Ani',
    ]);

    $quote = app(PricingService::class)->quote($product, 1, $customer, $pcs->id);

    expect($quote->price)->toBe(20_000.0);
    expect($quote->source)->toBe('price_level');
});

it('memakai price rule paling spesifik dan menghitung margin', function (): void {
    ['company' => $company] = $this->bootCompany();
    ['product' => $product, 'dus' => $dus] = seedProduct($company->id);

    // Tier umum: berlaku dari 12 pcs.
    PriceRule::create([
        'company_id' => $company->id,
        'name' => 'Tier 1 Dus',
        'type' => PriceRuleType::QuantityTier,
        'product_id' => $product->id,
        'min_quantity' => 12,
        'mode' => 'discount_percent',
        'discount_percent' => 5,
    ]);

    // Tier lebih besar harus menang karena min_quantity tercapai.
    PriceRule::create([
        'company_id' => $company->id,
        'name' => 'Tier 5 Dus',
        'type' => PriceRuleType::QuantityTier,
        'product_id' => $product->id,
        'priority' => 5,
        'min_quantity' => 60,
        'mode' => 'discount_percent',
        'discount_percent' => 10,
    ]);

    $service = app(PricingService::class);

    $small = $service->quote($product, 2, null, $dus->id);
    $large = $service->quote($product, 5, null, $dus->id);

    expect($small->rule?->name)->toBe('Tier 1 Dus');
    expect($small->price)->toBe(round(18_000 * 12 * 0.95, 4));

    expect($large->rule?->name)->toBe('Tier 5 Dus');
    expect($large->price)->toBe(round(18_000 * 12 * 0.90, 4));
    expect($large->unitCost)->toBe(180_000.0);
});

it('meminta approval bila margin di bawah minimum', function (): void {
    ['company' => $company] = $this->bootCompany();
    ['product' => $product] = seedProduct($company->id);

    $product->update(['min_margin_percent' => 10]);

    $guard = app(MarginGuard::class);

    $healthy = $guard->evaluate($product, 18_000, 15_000);
    $thin = $guard->evaluate($product, 15_500, 15_000);

    expect($healthy['allowed'])->toBeTrue();
    expect($healthy['requires_approval'])->toBeFalse();

    expect($thin['requires_approval'])->toBeTrue();
    expect($guard->isBelowCost(14_000, 15_000))->toBeTrue();
});

it('menyimpan produk beserta satuan turunan dan harga level', function (): void {
    ['company' => $company, 'branch' => $branch] = $this->bootCompany();
    $user = $this->makeUser($company, 'Owner', $branch);

    $pcs = Unit::where('code', 'PCS')->firstOrFail();
    $dus = Unit::where('code', 'DUS')->firstOrFail();
    $level = PriceLevel::where('code', 'GROSIR')->firstOrFail();

    Livewire::actingAs($user)
        ->test(ProductForm::class)
        ->set('form.sku', 'GLA-001')
        ->set('form.name', 'Gula Pasir 1kg')
        ->set('form.base_unit_id', $pcs->id)
        ->set('form.base_price', 15_000)
        ->set('units', [[
            'unit_id' => $dus->id,
            'conversion_to_base' => 20,
            'barcode' => null,
            'allow_purchase' => true,
            'allow_sales' => true,
        ]])
        ->set('prices', [[
            'price_level_id' => $level->id,
            'unit_id' => $pcs->id,
            'price' => 15_500,
        ]])
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::where('sku', 'GLA-001')->firstOrFail();

    expect($product->units)->toHaveCount(2);
    expect($product->conversionFor($dus->id))->toBe(20.0);
    expect($product->prices)->toHaveCount(1);
});

it('menolak satuan turunan yang sama dengan satuan dasar', function (): void {
    ['company' => $company, 'branch' => $branch] = $this->bootCompany();
    $user = $this->makeUser($company, 'Owner', $branch);

    $pcs = Unit::where('code', 'PCS')->firstOrFail();

    Livewire::actingAs($user)
        ->test(ProductForm::class)
        ->set('form.sku', 'DUP-001')
        ->set('form.name', 'Produk Duplikat Satuan')
        ->set('form.base_unit_id', $pcs->id)
        ->set('units', [[
            'unit_id' => $pcs->id,
            'conversion_to_base' => 1,
            'barcode' => null,
            'allow_purchase' => true,
            'allow_sales' => true,
        ]])
        ->call('save')
        ->assertHasErrors(['units.0.unit_id']);
});
