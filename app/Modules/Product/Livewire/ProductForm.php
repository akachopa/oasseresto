<?php

declare(strict_types=1);

namespace App\Modules\Product\Livewire;

use App\Modules\Accounting\Models\Account;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Product\Models\Brand;
use App\Modules\Product\Models\PriceLevel;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductCategory;
use App\Modules\Product\Models\ProductPrice;
use App\Modules\Product\Models\ProductUnit;
use App\Modules\Product\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Form produk memerlukan baris dinamis (satuan turunan dan harga per level),
 * jadi bagian ini memakai Livewire sementara daftar produk tetap memakai
 * DataTables server-side.
 */
class ProductForm extends Component
{
    public ?int $productId = null;

    public array $form = [
        'sku' => '',
        'barcode' => '',
        'name' => '',
        'short_name' => '',
        'product_category_id' => null,
        'brand_id' => null,
        'base_unit_id' => null,
        'purchase_unit_id' => null,
        'sales_unit_id' => null,
        'tax_code_id' => null,
        'inventory_account_id' => null,
        'cogs_account_id' => null,
        'revenue_account_id' => null,
        'track_batch' => false,
        'track_expiry' => false,
        'is_stocked' => true,
        'minimum_stock' => 0,
        'maximum_stock' => 0,
        'reorder_point' => 0,
        'safety_stock' => 0,
        'lead_time_days' => 0,
        'base_price' => 0,
        'min_margin_percent' => null,
        'description' => '',
        'is_active' => true,
    ];

    /** @var array<int, array{unit_id: ?int, conversion_to_base: float, barcode: ?string, allow_purchase: bool, allow_sales: bool}> */
    public array $units = [];

    /** @var array<int, array{price_level_id: ?int, unit_id: ?int, price: float}> */
    public array $prices = [];

    public function mount(?Product $product = null): void
    {
        if ($product?->exists) {
            $this->productId = $product->getKey();
            $this->form = array_merge($this->form, $product->only(array_keys($this->form)));

            $this->units = $product->units()->where('is_base', false)->get()
                ->map(fn (ProductUnit $unit) => [
                    'unit_id' => $unit->unit_id,
                    'conversion_to_base' => $unit->conversion_to_base,
                    'barcode' => $unit->barcode,
                    'allow_purchase' => $unit->allow_purchase,
                    'allow_sales' => $unit->allow_sales,
                ])->all();

            $this->prices = $product->prices->map(fn (ProductPrice $price) => [
                'price_level_id' => $price->price_level_id,
                'unit_id' => $price->unit_id,
                'price' => $price->price,
            ])->all();

            return;
        }

        $this->form['base_unit_id'] = Unit::orderBy('code')->value('id');
        $this->addPriceRow();
    }

    public function addUnitRow(): void
    {
        $this->units[] = [
            'unit_id' => null,
            'conversion_to_base' => 1,
            'barcode' => null,
            'allow_purchase' => true,
            'allow_sales' => true,
        ];
    }

    public function removeUnitRow(int $index): void
    {
        unset($this->units[$index]);
        $this->units = array_values($this->units);
    }

    public function addPriceRow(): void
    {
        $this->prices[] = [
            'price_level_id' => PriceLevel::where('is_default', true)->value('id') ?? PriceLevel::value('id'),
            'unit_id' => $this->form['base_unit_id'],
            'price' => 0,
        ];
    }

    public function removePriceRow(int $index): void
    {
        unset($this->prices[$index]);
        $this->prices = array_values($this->prices);
    }

    public function save(): void
    {
        $companyId = (int) auth()->user()->company_id;

        $validated = $this->validate([
            'form.sku' => [
                'required', 'string', 'max:60',
                Rule::unique('products', 'sku')->where('company_id', $companyId)->ignore($this->productId),
            ],
            'form.barcode' => ['nullable', 'string', 'max:60'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.short_name' => ['nullable', 'string', 'max:100'],
            'form.product_category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'form.brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'form.base_unit_id' => ['required', 'integer', 'exists:units,id'],
            'form.purchase_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'form.sales_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'form.tax_code_id' => ['nullable', 'integer', 'exists:tax_codes,id'],
            'form.inventory_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'form.cogs_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'form.revenue_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'form.minimum_stock' => ['numeric', 'min:0'],
            'form.maximum_stock' => ['numeric', 'min:0'],
            'form.reorder_point' => ['numeric', 'min:0'],
            'form.safety_stock' => ['numeric', 'min:0'],
            'form.lead_time_days' => ['integer', 'min:0', 'max:365'],
            'form.base_price' => ['numeric', 'min:0'],
            'form.min_margin_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'form.description' => ['nullable', 'string'],
            'units.*.unit_id' => ['required', 'integer', 'exists:units,id', 'different:form.base_unit_id'],
            'units.*.conversion_to_base' => ['required', 'numeric', 'gt:0'],
            'units.*.barcode' => ['nullable', 'string', 'max:60'],
            'prices.*.price_level_id' => ['required', 'integer', 'exists:price_levels,id'],
            'prices.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'prices.*.price' => ['required', 'numeric', 'min:0'],
        ], [
            'units.*.unit_id.different' => 'Satuan turunan tidak boleh sama dengan satuan dasar.',
            'units.*.conversion_to_base.gt' => 'Konversi harus lebih besar dari nol.',
        ]);

        DB::transaction(function () use ($validated): void {
            $product = $this->productId
                ? Product::findOrFail($this->productId)
                : new Product;

            $product->fill($validated['form']);
            $product->track_batch = (bool) $this->form['track_batch'] || (bool) $this->form['track_expiry'];
            $product->track_expiry = (bool) $this->form['track_expiry'];
            $product->is_stocked = (bool) $this->form['is_stocked'];
            $product->is_active = (bool) $this->form['is_active'];
            $product->save();

            $this->syncUnits($product);
            $this->syncPrices($product);
        });

        session()->flash('status', 'Produk berhasil disimpan.');
        $this->redirectRoute('products.index', navigate: true);
    }

    public function render()
    {
        return view('product.livewire.product-form', [
            'categories' => ProductCategory::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'brands' => Brand::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'unitOptions' => Unit::where('is_active', true)->orderBy('code')->get(),
            'taxCodes' => TaxCode::where('is_active', true)->orderBy('code')->pluck('name', 'id'),
            'priceLevels' => PriceLevel::where('is_active', true)->orderBy('sequence')->pluck('name', 'id'),
            'accounts' => Account::postable()->orderBy('code')->get(),
        ]);
    }

    /**
     * Satuan dasar selalu tercatat dengan konversi 1 agar seluruh kalkulasi
     * stok punya titik acuan yang sama.
     */
    private function syncUnits(Product $product): void
    {
        $keep = [];

        $base = ProductUnit::updateOrCreate(
            ['product_id' => $product->getKey(), 'unit_id' => $product->base_unit_id],
            [
                'company_id' => $product->company_id,
                'conversion_to_base' => 1,
                'is_base' => true,
                'allow_purchase' => true,
                'allow_sales' => true,
            ],
        );

        $keep[] = $base->id;

        foreach ($this->units as $row) {
            $unit = ProductUnit::updateOrCreate(
                ['product_id' => $product->getKey(), 'unit_id' => $row['unit_id']],
                [
                    'company_id' => $product->company_id,
                    'conversion_to_base' => $row['conversion_to_base'],
                    'barcode' => $row['barcode'] ?: null,
                    'is_base' => false,
                    'allow_purchase' => (bool) ($row['allow_purchase'] ?? true),
                    'allow_sales' => (bool) ($row['allow_sales'] ?? true),
                ],
            );

            $keep[] = $unit->id;
        }

        ProductUnit::where('product_id', $product->getKey())->whereNotIn('id', $keep)->delete();
    }

    private function syncPrices(Product $product): void
    {
        $keep = [];

        foreach ($this->prices as $row) {
            $price = ProductPrice::updateOrCreate(
                [
                    'product_id' => $product->getKey(),
                    'price_level_id' => $row['price_level_id'],
                    'unit_id' => $row['unit_id'],
                ],
                [
                    'company_id' => $product->company_id,
                    'price' => $row['price'],
                    'is_active' => true,
                ],
            );

            $keep[] = $price->id;
        }

        ProductPrice::where('product_id', $product->getKey())->whereNotIn('id', $keep)->delete();
    }
}
