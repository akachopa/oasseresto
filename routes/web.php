<?php

declare(strict_types=1);

use App\Http\Controllers\BranchSwitchController;
use App\Http\Controllers\PwaController;
use App\Modules\Auth\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/pwa/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/pwa/service-worker.js', [PwaController::class, 'serviceWorker'])->name('pwa.service-worker');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'index'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::post('/branch/switch', BranchSwitchController::class)->name('branch.switch');

    Route::redirect('/', '/dashboard');

    foreach (glob(__DIR__.'/modules/*.php') ?: [] as $moduleRoutes) {
        require $moduleRoutes;
    }
});
