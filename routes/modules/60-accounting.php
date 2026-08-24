<?php

declare(strict_types=1);

use App\Modules\Accounting\Controllers\AccountController;
use App\Modules\Accounting\Controllers\JournalController;
use App\Modules\Accounting\Controllers\LedgerController;
use App\Modules\Accounting\Controllers\PeriodController;
use App\Modules\Accounting\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/akuntansi', [ReportController::class, 'home'])
    ->middleware('permission:accounting.report.view')
    ->name('home.accounting');

Route::prefix('akuntansi')->name('accounting.')->group(function (): void {
    Route::middleware('permission:accounting.coa.view')->prefix('akun')->name('accounts.')->group(function (): void {
        Route::get('/', [AccountController::class, 'index'])->name('index');
        Route::post('data', [AccountController::class, 'data'])->name('data');

        Route::middleware('permission:accounting.coa.manage')->group(function (): void {
            Route::get('tambah', [AccountController::class, 'create'])->name('create');
            Route::post('/', [AccountController::class, 'store'])->name('store');
            Route::get('{id}/ubah', [AccountController::class, 'edit'])->name('edit');
            Route::put('{id}', [AccountController::class, 'update'])->name('update');
            Route::delete('{id}', [AccountController::class, 'hapus'])->name('hapus');
        });
    });

    Route::middleware('permission:accounting.journal.view')->prefix('jurnal')->name('journals.')->group(function (): void {
        Route::get('/', [JournalController::class, 'index'])->name('index');
        Route::post('data', [JournalController::class, 'data'])->name('data');
        Route::get('{journal}/detail', [JournalController::class, 'detail'])->name('detail');

        Route::middleware('permission:accounting.journal.create')->group(function (): void {
            Route::get('tambah', [JournalController::class, 'create'])->name('create');
            Route::post('/', [JournalController::class, 'store'])->name('store');
        });

        Route::post('{journal}/reversal', [JournalController::class, 'reverse'])
            ->middleware('permission:accounting.journal.reverse')
            ->name('reverse');
    });

    Route::get('buku-besar', [LedgerController::class, 'index'])
        ->middleware('permission:accounting.journal.view')
        ->name('ledger');

    Route::middleware('permission:accounting.report.view')->prefix('laporan')->name('reports.')->group(function (): void {
        Route::get('neraca-saldo', [ReportController::class, 'trialBalance'])->name('trial-balance');
        Route::get('laba-rugi', [ReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('neraca', [ReportController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('arus-kas', [ReportController::class, 'cashFlow'])->name('cash-flow');
    });

    Route::middleware('permission:accounting.coa.view')->prefix('periode')->name('periods.')->group(function (): void {
        Route::get('/', [PeriodController::class, 'index'])->name('index');
        Route::post('{period}/tutup', [PeriodController::class, 'close'])
            ->middleware('permission:accounting.period.close')
            ->name('close');
        Route::post('{period}/buka', [PeriodController::class, 'reopen'])
            ->middleware('permission:accounting.period.reopen')
            ->name('reopen');
    });
});
