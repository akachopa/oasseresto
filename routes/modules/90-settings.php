<?php

declare(strict_types=1);

use App\Modules\Auth\Controllers\AuditController;
use App\Modules\Auth\Controllers\RoleController;
use App\Modules\Auth\Controllers\UserController;
use App\Modules\Company\Controllers\BranchController;
use App\Modules\Company\Controllers\CompanySettingController;
use App\Modules\Company\Controllers\WarehouseController;
use App\Modules\Core\Controllers\TaxCodeController;
use Illuminate\Support\Facades\Route;

Route::prefix('pengaturan')->name('settings.')->group(function (): void {
    Route::middleware('permission:setting.company.manage')->group(function (): void {
        Route::get('company', [CompanySettingController::class, 'index'])->name('company');
        Route::put('company', [CompanySettingController::class, 'update'])->name('company.update');

        Route::prefix('kode-pajak')->name('tax-codes.')->group(function (): void {
            Route::get('/', [TaxCodeController::class, 'index'])->name('index');
            Route::post('data', [TaxCodeController::class, 'data'])->name('data');
            Route::get('tambah', [TaxCodeController::class, 'create'])->name('create');
            Route::post('/', [TaxCodeController::class, 'store'])->name('store');
            Route::get('{taxCode}/ubah', [TaxCodeController::class, 'edit'])->name('edit');
            Route::put('{taxCode}', [TaxCodeController::class, 'update'])->name('update');
            Route::delete('{taxCode}', [TaxCodeController::class, 'hapus'])->name('hapus');
        });
    });

    Route::middleware('permission:setting.branch.manage')->group(function (): void {
        Route::prefix('cabang')->name('branches.')->group(function (): void {
            Route::get('/', [BranchController::class, 'index'])->name('index');
            Route::post('data', [BranchController::class, 'data'])->name('data');
            Route::get('tambah', [BranchController::class, 'create'])->name('create');
            Route::post('/', [BranchController::class, 'store'])->name('store');
            Route::get('{branch}/ubah', [BranchController::class, 'edit'])->name('edit');
            Route::put('{branch}', [BranchController::class, 'update'])->name('update');
            Route::delete('{branch}', [BranchController::class, 'hapus'])->name('hapus');
        });

        Route::prefix('gudang')->name('warehouses.')->group(function (): void {
            Route::get('/', [WarehouseController::class, 'index'])->name('index');
            Route::post('data', [WarehouseController::class, 'data'])->name('data');
            Route::get('tambah', [WarehouseController::class, 'create'])->name('create');
            Route::post('/', [WarehouseController::class, 'store'])->name('store');
            Route::get('{warehouse}/ubah', [WarehouseController::class, 'edit'])->name('edit');
            Route::put('{warehouse}', [WarehouseController::class, 'update'])->name('update');
            Route::delete('{warehouse}', [WarehouseController::class, 'hapus'])->name('hapus');
        });
    });
});

Route::prefix('tim')->name('team.')->group(function (): void {
    Route::middleware('permission:setting.user.manage')->prefix('user')->name('users.')->group(function (): void {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('data', [UserController::class, 'data'])->name('data');
        Route::get('tambah', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('{user}/ubah', [UserController::class, 'edit'])->name('edit');
        Route::put('{user}', [UserController::class, 'update'])->name('update');
        Route::delete('{user}', [UserController::class, 'hapus'])->name('hapus');
    });

    Route::middleware('permission:setting.role.manage')->prefix('role')->name('roles.')->group(function (): void {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::get('tambah', [RoleController::class, 'create'])->name('create');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::get('{role}/ubah', [RoleController::class, 'edit'])->name('edit');
        Route::put('{role}', [RoleController::class, 'update'])->name('update');
        Route::delete('{role}', [RoleController::class, 'hapus'])->name('hapus');
    });

    Route::middleware('permission:setting.audit.view')->prefix('audit')->name('audit.')->group(function (): void {
        Route::get('/', [AuditController::class, 'index'])->name('index');
        Route::post('data', [AuditController::class, 'data'])->name('data');
    });
});
