<?php

declare(strict_types=1);

use App\Modules\Core\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/lainnya', [DashboardController::class, 'more'])->name('more');
