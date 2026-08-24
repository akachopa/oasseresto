<?php

declare(strict_types=1);

use App\Modules\Finance\Controllers\CashAccountController;
use App\Modules\Finance\Controllers\ExpenseController;
use App\Modules\Finance\Controllers\PayableController;
use App\Modules\Finance\Controllers\PaymentController;
use App\Modules\Finance\Controllers\ReceiptController;
use App\Modules\Finance\Controllers\ReceivableController;
use Illuminate\Support\Facades\Route;

Route::prefix('keuangan')->name('finance.')->group(function (): void {
    Route::middleware('permission:finance.cash.view')->prefix('kas')->name('cash.')->group(function (): void {
        Route::get('/', [CashAccountController::class, 'index'])->name('index');
        Route::post('data', [CashAccountController::class, 'data'])->name('data');
        Route::get('{cashAccount}/detail', [CashAccountController::class, 'detail'])->name('detail');

        Route::middleware('permission:finance.cash.manage')->group(function (): void {
            Route::get('tambah', [CashAccountController::class, 'create'])->name('create');
            Route::post('/', [CashAccountController::class, 'store'])->name('store');
            Route::get('{cashAccount}/ubah', [CashAccountController::class, 'edit'])->name('edit');
            Route::put('{cashAccount}', [CashAccountController::class, 'update'])->name('update');
            Route::post('{cashAccount}/transfer', [CashAccountController::class, 'transfer'])->name('transfer');
            Route::delete('{cashAccount}', [CashAccountController::class, 'hapus'])->name('hapus');
        });
    });

    Route::middleware('permission:finance.receivable.view')->prefix('piutang')->name('receivables.')->group(function (): void {
        Route::get('/', [ReceivableController::class, 'index'])->name('index');
        Route::post('data', [ReceivableController::class, 'data'])->name('data');
        Route::get('umur', [ReceivableController::class, 'aging'])->name('aging');
    });

    Route::middleware('permission:finance.receivable.collect')->prefix('penerimaan')->name('receipts.')->group(function (): void {
        Route::get('/', [ReceiptController::class, 'index'])->name('index');
        Route::post('data', [ReceiptController::class, 'data'])->name('data');
        Route::get('tambah', [ReceiptController::class, 'create'])->name('create');
        Route::get('{receipt}/ubah', [ReceiptController::class, 'edit'])->name('edit');
        Route::get('{receipt}/detail', [ReceiptController::class, 'detail'])->name('detail');
        Route::post('{receipt}/posting', [ReceiptController::class, 'post'])->name('post');
        Route::post('{receipt}/cair', [ReceiptController::class, 'clear'])->name('clear');
        Route::delete('{receipt}', [ReceiptController::class, 'hapus'])->name('hapus');
    });

    Route::middleware('permission:finance.payable.view')->prefix('hutang')->name('payables.')->group(function (): void {
        Route::get('/', [PayableController::class, 'index'])->name('index');
        Route::post('data', [PayableController::class, 'data'])->name('data');
        Route::get('umur', [PayableController::class, 'aging'])->name('aging');
    });

    Route::middleware('permission:finance.payable.pay')->prefix('pembayaran')->name('payments.')->group(function (): void {
        Route::get('/', [PaymentController::class, 'index'])->name('index');
        Route::post('data', [PaymentController::class, 'data'])->name('data');
        Route::get('tambah', [PaymentController::class, 'create'])->name('create');
        Route::get('{payment}/ubah', [PaymentController::class, 'edit'])->name('edit');
        Route::get('{payment}/detail', [PaymentController::class, 'detail'])->name('detail');
        Route::post('{payment}/posting', [PaymentController::class, 'post'])->name('post');
        Route::delete('{payment}', [PaymentController::class, 'hapus'])->name('hapus');
    });

    Route::prefix('biaya')->name('expenses.')->group(function (): void {
        Route::middleware('permission:finance.expense.view')->group(function (): void {
            Route::get('/', [ExpenseController::class, 'index'])->name('index');
            Route::post('data', [ExpenseController::class, 'data'])->name('data');
            Route::get('{expense}/detail', [ExpenseController::class, 'detail'])->name('detail');
        });

        Route::middleware('permission:finance.expense.create')->group(function (): void {
            Route::get('tambah', [ExpenseController::class, 'create'])->name('create');
            Route::post('/', [ExpenseController::class, 'store'])->name('store');
            Route::get('{expense}/ubah', [ExpenseController::class, 'edit'])->name('edit');
            Route::put('{expense}', [ExpenseController::class, 'update'])->name('update');
            Route::post('{expense}/ajukan', [ExpenseController::class, 'submit'])->name('submit');
            Route::delete('{expense}', [ExpenseController::class, 'hapus'])->name('hapus');
        });

        Route::middleware('permission:finance.expense.approve')->group(function (): void {
            Route::post('{expense}/setujui', [ExpenseController::class, 'approve'])->name('approve');
            Route::post('{expense}/tolak', [ExpenseController::class, 'reject'])->name('reject');
        });

        Route::post('{expense}/posting', [ExpenseController::class, 'post'])
            ->middleware('permission:finance.expense.post')->name('post');
    });
});
