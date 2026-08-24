<?php

declare(strict_types=1);

use App\Modules\Purchase\Controllers\GoodsReceiptController;
use App\Modules\Purchase\Controllers\PurchaseInvoiceController;
use App\Modules\Purchase\Controllers\PurchaseOrderController;
use App\Modules\Purchase\Controllers\PurchaseRequestController;
use App\Modules\Purchase\Controllers\PurchaseReturnController;
use App\Modules\Purchase\Controllers\ReorderController;
use Illuminate\Support\Facades\Route;

Route::prefix('pembelian')->name('purchase.')->group(function (): void {
    Route::middleware('permission:purchase.view')->prefix('rekomendasi')->name('reorder.')->group(function (): void {
        Route::get('/', [ReorderController::class, 'index'])->name('index');
        Route::post('data', [ReorderController::class, 'data'])->name('data');
        Route::post('purchase-request', [ReorderController::class, 'store'])
            ->middleware('permission:purchase.create')->name('store');
    });

    Route::middleware('permission:purchase.view')->prefix('permintaan')->name('requests.')->group(function (): void {
        Route::get('/', [PurchaseRequestController::class, 'index'])->name('index');
        Route::post('data', [PurchaseRequestController::class, 'data'])->name('data');
        Route::get('{purchaseRequest}/detail', [PurchaseRequestController::class, 'detail'])->name('detail');

        Route::middleware('permission:purchase.create')->group(function (): void {
            Route::get('tambah', [PurchaseRequestController::class, 'create'])->name('create');
            Route::post('{purchaseRequest}/ajukan', [PurchaseRequestController::class, 'submit'])->name('submit');
        });

        Route::get('{purchaseRequest}/ubah', [PurchaseRequestController::class, 'edit'])
            ->middleware('permission:purchase.edit')->name('edit');

        Route::middleware('permission:purchase.approve')->group(function (): void {
            Route::post('{purchaseRequest}/setujui', [PurchaseRequestController::class, 'approve'])->name('approve');
            Route::post('{purchaseRequest}/tolak', [PurchaseRequestController::class, 'reject'])->name('reject');
        });

        Route::delete('{purchaseRequest}', [PurchaseRequestController::class, 'hapus'])
            ->middleware('permission:purchase.delete')->name('hapus');
    });

    Route::middleware('permission:purchase.view')->prefix('order')->name('orders.')->group(function (): void {
        Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
        Route::post('data', [PurchaseOrderController::class, 'data'])->name('data');
        Route::get('{purchaseOrder}/detail', [PurchaseOrderController::class, 'detail'])->name('detail');
        Route::get('{purchaseOrder}/cetak', [PurchaseOrderController::class, 'print'])->name('print');

        Route::middleware('permission:purchase.create')->group(function (): void {
            Route::get('tambah', [PurchaseOrderController::class, 'create'])->name('create');
            Route::post('dari-permintaan/{purchaseRequest}', [PurchaseOrderController::class, 'fromRequest'])
                ->name('from-request');
            Route::post('{purchaseOrder}/ajukan', [PurchaseOrderController::class, 'submit'])->name('submit');
        });

        Route::get('{purchaseOrder}/ubah', [PurchaseOrderController::class, 'edit'])
            ->middleware('permission:purchase.edit')->name('edit');

        Route::middleware('permission:purchase.approve')->group(function (): void {
            Route::post('{purchaseOrder}/setujui', [PurchaseOrderController::class, 'approve'])->name('approve');
            Route::post('{purchaseOrder}/tolak', [PurchaseOrderController::class, 'reject'])->name('reject');
            Route::post('{purchaseOrder}/tutup', [PurchaseOrderController::class, 'close'])->name('close');
        });

        Route::delete('{purchaseOrder}', [PurchaseOrderController::class, 'hapus'])
            ->middleware('permission:purchase.delete')->name('hapus');
    });

    Route::middleware('permission:inventory.receive')->prefix('penerimaan')->name('receipts.')->group(function (): void {
        Route::get('/', [GoodsReceiptController::class, 'index'])->name('index');
        Route::post('data', [GoodsReceiptController::class, 'data'])->name('data');
        Route::get('tambah', [GoodsReceiptController::class, 'create'])->name('create');
        Route::get('{goodsReceipt}/ubah', [GoodsReceiptController::class, 'edit'])->name('edit');
        Route::get('{goodsReceipt}/detail', [GoodsReceiptController::class, 'detail'])->name('detail');
        Route::post('{goodsReceipt}/posting', [GoodsReceiptController::class, 'post'])->name('post');
        Route::delete('{goodsReceipt}', [GoodsReceiptController::class, 'hapus'])->name('hapus');
    });

    Route::middleware('permission:purchase.view')->prefix('invoice')->name('invoices.')->group(function (): void {
        Route::get('/', [PurchaseInvoiceController::class, 'index'])->name('index');
        Route::post('data', [PurchaseInvoiceController::class, 'data'])->name('data');
        Route::get('{purchaseInvoice}/detail', [PurchaseInvoiceController::class, 'detail'])->name('detail');

        Route::middleware('permission:purchase.create')->group(function (): void {
            Route::get('tambah', [PurchaseInvoiceController::class, 'create'])->name('create');
            Route::get('{purchaseInvoice}/ubah', [PurchaseInvoiceController::class, 'edit'])->name('edit');
        });

        Route::post('{purchaseInvoice}/posting', [PurchaseInvoiceController::class, 'post'])
            ->middleware('permission:purchase.post')->name('post');

        Route::delete('{purchaseInvoice}', [PurchaseInvoiceController::class, 'hapus'])
            ->middleware('permission:purchase.delete')->name('hapus');
    });

    Route::middleware('permission:purchase.return')->prefix('retur')->name('returns.')->group(function (): void {
        Route::get('/', [PurchaseReturnController::class, 'index'])->name('index');
        Route::post('data', [PurchaseReturnController::class, 'data'])->name('data');
        Route::get('tambah', [PurchaseReturnController::class, 'create'])->name('create');
        Route::get('{purchaseReturn}/ubah', [PurchaseReturnController::class, 'edit'])->name('edit');
        Route::get('{purchaseReturn}/detail', [PurchaseReturnController::class, 'detail'])->name('detail');
        Route::post('{purchaseReturn}/posting', [PurchaseReturnController::class, 'post'])->name('post');
        Route::delete('{purchaseReturn}', [PurchaseReturnController::class, 'hapus'])->name('hapus');
    });
});
