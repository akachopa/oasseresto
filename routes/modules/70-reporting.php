<?php

declare(strict_types=1);

use App\Modules\Reporting\Controllers\HomeController;
use App\Modules\Reporting\Controllers\InsightController;
use App\Modules\Reporting\Controllers\ReportController;
use App\Modules\Reporting\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/tugas', [TaskController::class, 'index'])
    ->middleware('permission:dashboard.view')
    ->name('tasks.index');

Route::middleware('permission:dashboard.view')->group(function (): void {
    Route::get('/home/gudang', [HomeController::class, 'warehouse'])
        ->middleware('role:Warehouse Staff|Warehouse Supervisor')
        ->name('home.warehouse');

    Route::get('/home/pembelian', [HomeController::class, 'purchasing'])
        ->middleware('role:Purchasing')
        ->name('home.purchasing');

    Route::get('/home/salesman', [HomeController::class, 'salesman'])
        ->middleware('role:Salesman|Sales Supervisor')
        ->name('home.salesman');

    Route::get('/home/keuangan', [HomeController::class, 'finance'])
        ->middleware('role:Finance')
        ->name('home.finance');
});

Route::prefix('insight')->name('insight.')->group(function (): void {
    Route::get('/', [InsightController::class, 'index'])
        ->middleware('permission:report.profit')
        ->name('index');

    Route::get('proyeksi-kas', [InsightController::class, 'cashForecast'])
        ->middleware('permission:report.finance')
        ->name('cash-forecast');

    Route::get('pergerakan-stok', [InsightController::class, 'stockMovement'])
        ->middleware('permission:report.inventory')
        ->name('stock-movement');

    Route::get('profitabilitas', [InsightController::class, 'profitability'])
        ->middleware('permission:report.profit')
        ->name('profitability');
});

Route::prefix('laporan')->name('reports.')->group(function (): void {
    Route::get('penjualan', [ReportController::class, 'sales'])
        ->middleware('permission:report.sales')
        ->name('sales');

    Route::get('pembelian', [ReportController::class, 'purchase'])
        ->middleware('permission:report.purchase')
        ->name('purchase');

    Route::get('nilai-persediaan', [ReportController::class, 'inventoryValuation'])
        ->middleware('permission:inventory.valuation.view')
        ->name('inventory-valuation');

    Route::get('umur-piutang', [ReportController::class, 'arAging'])
        ->middleware('permission:finance.receivable.view')
        ->name('ar-aging');

    Route::get('umur-hutang', [ReportController::class, 'apAging'])
        ->middleware('permission:finance.payable.view')
        ->name('ap-aging');

    Route::get('laba-cabang', [ReportController::class, 'profitByBranch'])
        ->middleware('permission:report.profit')
        ->name('profit-by-branch');

    Route::get('laba-produk', [ReportController::class, 'profitByProduct'])
        ->middleware('permission:report.profit')
        ->name('profit-by-product');
});
