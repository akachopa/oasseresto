<?php

declare(strict_types=1);

use App\Modules\Delivery\Controllers\DeliveryController;
use App\Modules\Sales\Controllers\PosController;
use App\Modules\Sales\Controllers\QuotationController;
use App\Modules\Sales\Controllers\SalesInvoiceController;
use App\Modules\Sales\Controllers\SalesOrderController;
use App\Modules\Sales\Controllers\SalesReturnController;
use Illuminate\Support\Facades\Route;

Route::prefix('penjualan')->name('sales.')->group(function (): void {
    Route::middleware('permission:sales.view')->prefix('penawaran')->name('quotations.')->group(function (): void {
        Route::get('/', [QuotationController::class, 'index'])->name('index');
        Route::post('data', [QuotationController::class, 'data'])->name('data');
        Route::get('{quotation}/detail', [QuotationController::class, 'detail'])->name('detail');

        Route::middleware('permission:sales.create')->group(function (): void {
            Route::get('tambah', [QuotationController::class, 'create'])->name('create');
            Route::post('{quotation}/kirim', [QuotationController::class, 'send'])->name('send');
            Route::post('{quotation}/diterima', [QuotationController::class, 'accept'])->name('accept');
        });

        Route::get('{quotation}/ubah', [QuotationController::class, 'edit'])
            ->middleware('permission:sales.edit')->name('edit');

        Route::delete('{quotation}', [QuotationController::class, 'hapus'])
            ->middleware('permission:sales.delete')->name('hapus');
    });

    Route::middleware('permission:sales.view')->prefix('order')->name('orders.')->group(function (): void {
        Route::get('/', [SalesOrderController::class, 'index'])->name('index');
        Route::post('data', [SalesOrderController::class, 'data'])->name('data');
        Route::get('{salesOrder}/detail', [SalesOrderController::class, 'detail'])->name('detail');

        Route::middleware('permission:sales.create')->group(function (): void {
            Route::get('tambah', [SalesOrderController::class, 'create'])->name('create');
            Route::post('dari-penawaran/{quotation}', [SalesOrderController::class, 'fromQuotation'])
                ->name('from-quotation');
            Route::post('{salesOrder}/ajukan', [SalesOrderController::class, 'submit'])->name('submit');
        });

        Route::get('{salesOrder}/ubah', [SalesOrderController::class, 'edit'])
            ->middleware('permission:sales.edit')->name('edit');

        Route::middleware('permission:sales.approve')->group(function (): void {
            Route::post('{salesOrder}/setujui', [SalesOrderController::class, 'approve'])->name('approve');
            Route::post('{salesOrder}/tolak', [SalesOrderController::class, 'reject'])->name('reject');
            Route::post('{salesOrder}/tutup', [SalesOrderController::class, 'close'])->name('close');
        });

        Route::delete('{salesOrder}', [SalesOrderController::class, 'hapus'])
            ->middleware('permission:sales.cancel')->name('hapus');
    });

    Route::middleware('permission:sales.view')->prefix('invoice')->name('invoices.')->group(function (): void {
        Route::get('/', [SalesInvoiceController::class, 'index'])->name('index');
        Route::post('data', [SalesInvoiceController::class, 'data'])->name('data');
        Route::get('{salesInvoice}/detail', [SalesInvoiceController::class, 'detail'])->name('detail');
        Route::get('{salesInvoice}/cetak', [SalesInvoiceController::class, 'print'])->name('print');

        Route::middleware('permission:sales.create')->group(function (): void {
            Route::get('tambah', [SalesInvoiceController::class, 'create'])->name('create');
            Route::get('{salesInvoice}/ubah', [SalesInvoiceController::class, 'edit'])->name('edit');
        });

        Route::post('{salesInvoice}/posting', [SalesInvoiceController::class, 'post'])
            ->middleware('permission:sales.post')->name('post');

        Route::delete('{salesInvoice}', [SalesInvoiceController::class, 'hapus'])
            ->middleware('permission:sales.delete')->name('hapus');
    });

    Route::middleware('permission:sales.return')->prefix('retur')->name('returns.')->group(function (): void {
        Route::get('/', [SalesReturnController::class, 'index'])->name('index');
        Route::post('data', [SalesReturnController::class, 'data'])->name('data');
        Route::get('tambah', [SalesReturnController::class, 'create'])->name('create');
        Route::get('{salesReturn}/ubah', [SalesReturnController::class, 'edit'])->name('edit');
        Route::get('{salesReturn}/detail', [SalesReturnController::class, 'detail'])->name('detail');
        Route::post('{salesReturn}/posting', [SalesReturnController::class, 'post'])->name('post');
        Route::delete('{salesReturn}', [SalesReturnController::class, 'hapus'])->name('hapus');
    });
});

Route::prefix('pengiriman')->name('delivery.')->group(function (): void {
    Route::middleware('permission:inventory.pick')->prefix('picking')->name('picking.')->group(function (): void {
        Route::get('/', [DeliveryController::class, 'picking'])->name('index');
        Route::post('data', [DeliveryController::class, 'pickingData'])->name('data');
        Route::get('{delivery}', [DeliveryController::class, 'pick'])->name('pick');
        Route::post('{delivery}', [DeliveryController::class, 'storePick'])->name('store');
    });

    Route::middleware('permission:delivery.view')->prefix('surat-jalan')->name('orders.')->group(function (): void {
        Route::get('/', [DeliveryController::class, 'index'])->name('index');
        Route::post('data', [DeliveryController::class, 'data'])->name('data');
        Route::get('{delivery}/detail', [DeliveryController::class, 'detail'])->name('detail');
        Route::get('{delivery}/cetak', [DeliveryController::class, 'print'])->name('print');

        Route::middleware('permission:delivery.create')->group(function (): void {
            Route::get('tambah', [DeliveryController::class, 'create'])->name('create');
            Route::get('{delivery}/ubah', [DeliveryController::class, 'edit'])->name('edit');
            Route::delete('{delivery}', [DeliveryController::class, 'hapus'])->name('hapus');
        });

        Route::post('{delivery}/kirim', [DeliveryController::class, 'dispatchDelivery'])
            ->middleware('permission:delivery.dispatch')->name('dispatch');

        Route::middleware('permission:delivery.complete')->group(function (): void {
            Route::post('{delivery}/selesai', [DeliveryController::class, 'complete'])->name('complete');
            Route::post('{delivery}/gagal', [DeliveryController::class, 'fail'])->name('fail');
        });
    });
});

Route::prefix('kasir')->name('pos.')->middleware('permission:pos.view')->group(function (): void {
    Route::get('/', [PosController::class, 'index'])->name('index');
    Route::get('shift', [PosController::class, 'shift'])->name('shift');
    Route::get('shift/{shift}', [PosController::class, 'shiftDetail'])->name('shift.detail');

    Route::post('shift/buka', [PosController::class, 'openShift'])
        ->middleware('permission:pos.shift.open')->name('shift.open');

    Route::post('shift/{shift}/tutup', [PosController::class, 'closeShift'])
        ->middleware('permission:pos.shift.close')->name('shift.close');

    Route::post('shift/{shift}/kas', [PosController::class, 'cashMovement'])
        ->middleware('permission:pos.sell')->name('shift.cash');
});
