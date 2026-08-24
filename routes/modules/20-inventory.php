<?php

declare(strict_types=1);

use App\Modules\Inventory\Controllers\StockAdjustmentController;
use App\Modules\Inventory\Controllers\StockController;
use App\Modules\Inventory\Controllers\StockOpnameController;
use App\Modules\Inventory\Controllers\StockTransferController;
use Illuminate\Support\Facades\Route;

Route::prefix('stok')->name('inventory.')->group(function (): void {
    Route::middleware('permission:inventory.view')->group(function (): void {
        Route::prefix('saldo')->name('stock.')->group(function (): void {
            Route::get('/', [StockController::class, 'index'])->name('index');
            Route::post('data', [StockController::class, 'data'])->name('data');
            Route::get('{product}/kartu', [StockController::class, 'detail'])->name('detail');
        });

        Route::prefix('kartu-stok')->name('ledger.')->group(function (): void {
            Route::get('/', [StockController::class, 'ledger'])->name('index');
            Route::post('data', [StockController::class, 'ledgerData'])->name('data');
        });

        Route::prefix('batch')->name('batches.')->group(function (): void {
            Route::get('/', [StockController::class, 'batches'])->name('index');
            Route::post('data', [StockController::class, 'batchData'])->name('data');
        });
    });

    Route::middleware('permission:inventory.transfer')->prefix('transfer')->name('transfers.')->group(function (): void {
        Route::get('/', [StockTransferController::class, 'index'])->name('index');
        Route::post('data', [StockTransferController::class, 'data'])->name('data');
        Route::get('tambah', [StockTransferController::class, 'create'])->name('create');
        Route::get('{stockTransfer}/ubah', [StockTransferController::class, 'edit'])->name('edit');
        Route::get('{stockTransfer}/detail', [StockTransferController::class, 'detail'])->name('detail');
        Route::post('{stockTransfer}/kirim', [StockTransferController::class, 'ship'])->name('ship');
        Route::post('{stockTransfer}/terima', [StockTransferController::class, 'receive'])->name('receive');
        Route::delete('{stockTransfer}', [StockTransferController::class, 'hapus'])->name('hapus');
    });

    Route::middleware('permission:inventory.adjust')->prefix('penyesuaian')->name('adjustments.')->group(function (): void {
        Route::get('/', [StockAdjustmentController::class, 'index'])->name('index');
        Route::post('data', [StockAdjustmentController::class, 'data'])->name('data');
        Route::get('tambah', [StockAdjustmentController::class, 'create'])->name('create');
        Route::get('{stockAdjustment}/ubah', [StockAdjustmentController::class, 'edit'])->name('edit');
        Route::get('{stockAdjustment}/detail', [StockAdjustmentController::class, 'detail'])->name('detail');
        Route::post('{stockAdjustment}/posting', [StockAdjustmentController::class, 'post'])->name('post');
        Route::delete('{stockAdjustment}', [StockAdjustmentController::class, 'hapus'])->name('hapus');
    });

    Route::middleware('permission:inventory.opname')->prefix('opname')->name('opnames.')->group(function (): void {
        Route::get('/', [StockOpnameController::class, 'index'])->name('index');
        Route::post('data', [StockOpnameController::class, 'data'])->name('data');
        Route::get('tambah', [StockOpnameController::class, 'create'])->name('create');
        Route::post('/', [StockOpnameController::class, 'store'])->name('store');
        Route::get('{stockOpname}/hitung', [StockOpnameController::class, 'count'])->name('count');
        Route::get('{stockOpname}/detail', [StockOpnameController::class, 'detail'])->name('detail');
        Route::post('{stockOpname}/posting', [StockOpnameController::class, 'post'])->name('post');
        Route::delete('{stockOpname}', [StockOpnameController::class, 'hapus'])->name('hapus');
    });
});
