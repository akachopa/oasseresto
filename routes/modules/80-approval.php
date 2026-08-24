<?php

declare(strict_types=1);

use App\Modules\Approval\Controllers\ApprovalRequestController;
use App\Modules\Approval\Controllers\ApprovalRuleController;
use Illuminate\Support\Facades\Route;

Route::prefix('approval')->name('approval.')->group(function (): void {
    Route::middleware('permission:approval.rule.manage')->prefix('aturan')->name('rules.')->group(function (): void {
        Route::get('/', [ApprovalRuleController::class, 'index'])->name('index');
        Route::post('data', [ApprovalRuleController::class, 'data'])->name('data');
        Route::get('tambah', [ApprovalRuleController::class, 'create'])->name('create');
        Route::post('/', [ApprovalRuleController::class, 'store'])->name('store');
        Route::get('{rule}/ubah', [ApprovalRuleController::class, 'edit'])->name('edit');
        Route::put('{rule}', [ApprovalRuleController::class, 'update'])->name('update');
        Route::delete('{rule}', [ApprovalRuleController::class, 'hapus'])->name('hapus');
    });

    Route::middleware('permission:approval.view')->prefix('permintaan')->name('requests.')->group(function (): void {
        Route::get('/', [ApprovalRequestController::class, 'index'])->name('index');
        Route::post('data', [ApprovalRequestController::class, 'data'])->name('data');
        Route::get('{request}/detail', [ApprovalRequestController::class, 'detail'])->name('detail');

        Route::middleware('permission:approval.act')->group(function (): void {
            Route::post('{request}/setujui', [ApprovalRequestController::class, 'approve'])->name('approve');
            Route::post('{request}/tolak', [ApprovalRequestController::class, 'reject'])->name('reject');
        });
    });
});
