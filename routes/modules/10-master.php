<?php

declare(strict_types=1);

use App\Modules\Customer\Controllers\CustomerController;
use App\Modules\Customer\Controllers\CustomerGroupController;
use App\Modules\Product\Controllers\BrandController;
use App\Modules\Product\Controllers\PriceLevelController;
use App\Modules\Product\Controllers\PriceRuleController;
use App\Modules\Product\Controllers\ProductCategoryController;
use App\Modules\Product\Controllers\ProductController;
use App\Modules\Product\Controllers\UnitController;
use App\Modules\Supplier\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::prefix('barang')->group(function (): void {
    Route::middleware('permission:product.view')->group(function (): void {
        Route::prefix('produk')->name('products.')->group(function (): void {
            Route::get('/', [ProductController::class, 'index'])->name('index');
            Route::post('data', [ProductController::class, 'data'])->name('data');
            Route::get('{product}/detail', [ProductController::class, 'detail'])->name('detail');

            Route::middleware('permission:product.create')
                ->get('tambah', [ProductController::class, 'create'])->name('create');
            Route::middleware('permission:product.edit')
                ->get('{product}/ubah', [ProductController::class, 'edit'])->name('edit');
            Route::middleware('permission:product.delete')
                ->delete('{product}', [ProductController::class, 'hapus'])->name('hapus');
        });

        Route::prefix('kategori')->name('product-categories.')->group(function (): void {
            Route::get('/', [ProductCategoryController::class, 'index'])->name('index');
            Route::post('data', [ProductCategoryController::class, 'data'])->name('data');

            Route::middleware('permission:product.edit')->group(function (): void {
                Route::get('tambah', [ProductCategoryController::class, 'create'])->name('create');
                Route::post('/', [ProductCategoryController::class, 'store'])->name('store');
                Route::get('{id}/ubah', [ProductCategoryController::class, 'edit'])->name('edit');
                Route::put('{id}', [ProductCategoryController::class, 'update'])->name('update');
                Route::delete('{id}', [ProductCategoryController::class, 'hapus'])->name('hapus');
            });
        });

        Route::prefix('brand')->name('brands.')->group(function (): void {
            Route::get('/', [BrandController::class, 'index'])->name('index');
            Route::post('data', [BrandController::class, 'data'])->name('data');

            Route::middleware('permission:product.edit')->group(function (): void {
                Route::get('tambah', [BrandController::class, 'create'])->name('create');
                Route::post('/', [BrandController::class, 'store'])->name('store');
                Route::get('{id}/ubah', [BrandController::class, 'edit'])->name('edit');
                Route::put('{id}', [BrandController::class, 'update'])->name('update');
                Route::delete('{id}', [BrandController::class, 'hapus'])->name('hapus');
            });
        });

        Route::prefix('satuan')->name('units.')->group(function (): void {
            Route::get('/', [UnitController::class, 'index'])->name('index');
            Route::post('data', [UnitController::class, 'data'])->name('data');

            Route::middleware('permission:product.edit')->group(function (): void {
                Route::get('tambah', [UnitController::class, 'create'])->name('create');
                Route::post('/', [UnitController::class, 'store'])->name('store');
                Route::get('{id}/ubah', [UnitController::class, 'edit'])->name('edit');
                Route::put('{id}', [UnitController::class, 'update'])->name('update');
                Route::delete('{id}', [UnitController::class, 'hapus'])->name('hapus');
            });
        });
    });

    Route::middleware('permission:product.price.manage')->group(function (): void {
        Route::prefix('level-harga')->name('price-levels.')->group(function (): void {
            Route::get('/', [PriceLevelController::class, 'index'])->name('index');
            Route::post('data', [PriceLevelController::class, 'data'])->name('data');
            Route::get('tambah', [PriceLevelController::class, 'create'])->name('create');
            Route::post('/', [PriceLevelController::class, 'store'])->name('store');
            Route::get('{id}/ubah', [PriceLevelController::class, 'edit'])->name('edit');
            Route::put('{id}', [PriceLevelController::class, 'update'])->name('update');
            Route::delete('{id}', [PriceLevelController::class, 'hapus'])->name('hapus');
        });

        Route::prefix('aturan-harga')->name('price-rules.')->group(function (): void {
            Route::get('/', [PriceRuleController::class, 'index'])->name('index');
            Route::post('data', [PriceRuleController::class, 'data'])->name('data');
            Route::get('tambah', [PriceRuleController::class, 'create'])->name('create');
            Route::post('/', [PriceRuleController::class, 'store'])->name('store');
            Route::get('{priceRule}/ubah', [PriceRuleController::class, 'edit'])->name('edit');
            Route::put('{priceRule}', [PriceRuleController::class, 'update'])->name('update');
            Route::delete('{priceRule}', [PriceRuleController::class, 'hapus'])->name('hapus');
        });
    });
});

Route::prefix('customer')->group(function (): void {
    Route::middleware('permission:customer.view')->group(function (): void {
        Route::name('customers.')->group(function (): void {
            Route::get('/', [CustomerController::class, 'index'])->name('index');
            Route::post('data', [CustomerController::class, 'data'])->name('data');
            Route::get('{customer}/detail', [CustomerController::class, 'detail'])->name('detail');

            Route::middleware('permission:customer.create')
                ->get('tambah', [CustomerController::class, 'create'])->name('create');
            Route::middleware('permission:customer.edit')
                ->get('{customer}/ubah', [CustomerController::class, 'edit'])->name('edit');
            Route::middleware('permission:customer.delete')
                ->delete('{customer}', [CustomerController::class, 'hapus'])->name('hapus');
        });

        Route::prefix('grup')->name('customer-groups.')->group(function (): void {
            Route::get('/', [CustomerGroupController::class, 'index'])->name('index');
            Route::post('data', [CustomerGroupController::class, 'data'])->name('data');

            Route::middleware('permission:customer.edit')->group(function (): void {
                Route::get('tambah', [CustomerGroupController::class, 'create'])->name('create');
                Route::post('/', [CustomerGroupController::class, 'store'])->name('store');
                Route::get('{id}/ubah', [CustomerGroupController::class, 'edit'])->name('edit');
                Route::put('{id}', [CustomerGroupController::class, 'update'])->name('update');
                Route::delete('{id}', [CustomerGroupController::class, 'hapus'])->name('hapus');
            });
        });
    });
});

Route::prefix('supplier')->middleware('permission:supplier.view')->name('suppliers.')->group(function (): void {
    Route::get('/', [SupplierController::class, 'index'])->name('index');
    Route::post('data', [SupplierController::class, 'data'])->name('data');
    Route::get('performa', [SupplierController::class, 'performance'])->name('performance');
    Route::get('{supplier}/detail', [SupplierController::class, 'detail'])->name('detail');

    Route::middleware('permission:supplier.create')
        ->get('tambah', [SupplierController::class, 'create'])->name('create');
    Route::middleware('permission:supplier.edit')
        ->get('{supplier}/ubah', [SupplierController::class, 'edit'])->name('edit');
    Route::middleware('permission:supplier.delete')
        ->delete('{supplier}', [SupplierController::class, 'hapus'])->name('hapus');
});
