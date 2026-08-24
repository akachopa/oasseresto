<?php

declare(strict_types=1);

namespace App\Modules\Product\Controllers;

use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Product\Models\Brand;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductCategory;
use App\Modules\Supplier\Models\SupplierProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController
{
    public function index(): View
    {
        return view('product.product.index', [
            'categories' => ProductCategory::orderBy('name')->pluck('name', 'id'),
            'brands' => Brand::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $showCost = auth()->user()->can('product.cost.view');

        $query = Product::query()
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->leftJoin('units', 'units.id', '=', 'products.base_unit_id')
            ->select([
                'products.id', 'products.sku', 'products.name', 'products.barcode',
                'products.average_cost', 'products.base_price', 'products.is_active',
                'products.track_batch', 'products.track_expiry', 'products.reorder_point',
                'product_categories.name as category_name',
                'brands.name as brand_name',
                'units.code as unit_code',
            ]);

        return response()->json(
            ServerTable::of($query)
                ->searchable(['products.sku', 'products.name', 'products.barcode', 'brands.name'])
                ->orderable([
                    null, 'products.sku', 'products.name', 'product_categories.name',
                    'brands.name', 'units.code', 'products.base_price',
                    $showCost ? 'products.average_cost' : null, null, null,
                ])
                ->filter('category', fn ($q, $value) => $q->where('products.product_category_id', $value))
                ->filter('brand', fn ($q, $value) => $q->where('products.brand_id', $value))
                ->filter('is_active', fn ($q, $value) => $q->where('products.is_active', $value === '1'))
                ->transform(fn (Product $product) => [
                    'sku' => '<span class="font-mono text-xs">'.e($product->sku).'</span>',
                    'name' => '<a class="font-medium hover:text-brand-600" href="'.route('products.detail', $product).'">'
                        .e($product->name).'</a>'
                        .($product->track_expiry ? '<span class="badge-warning ml-1">Expiry</span>' : '')
                        .($product->track_batch && ! $product->track_expiry ? '<span class="badge-info ml-1">Batch</span>' : ''),
                    'category' => e((string) $product->category_name),
                    'brand' => e((string) $product->brand_name),
                    'unit' => e((string) $product->unit_code),
                    'price' => Money::rupiah($product->base_price),
                    'cost' => $showCost ? Money::rupiah($product->average_cost) : '<span class="text-muted">-</span>',
                    'status' => $product->is_active
                        ? '<span class="badge-success">Aktif</span>'
                        : '<span class="badge-muted">Nonaktif</span>',
                    'aksi' => view('components.row-actions', [
                        'detail' => route('products.detail', $product),
                        'edit' => auth()->user()->can('product.edit') ? route('products.edit', $product) : null,
                        'delete' => auth()->user()->can('product.delete') ? route('products.hapus', $product) : null,
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(): View
    {
        return view('product.product.create');
    }

    public function edit(Product $product): View
    {
        return view('product.product.edit', ['product' => $product]);
    }

    public function detail(Product $product): View
    {
        $product->load([
            'category', 'brand', 'baseUnit', 'units.unit',
            'prices.priceLevel', 'prices.unit', 'taxCode',
        ]);

        return view('product.product.detail', [
            'product' => $product,
            'suppliers' => $product->company_id
                ? SupplierProduct::with('supplier', 'unit')
                    ->where('product_id', $product->getKey())
                    ->orderByDesc('is_preferred')
                    ->get()
                : collect(),
        ]);
    }

    public function hapus(Product $product): RedirectResponse
    {
        $product->delete();

        return back()->with('status', 'Produk berhasil dihapus.');
    }
}
