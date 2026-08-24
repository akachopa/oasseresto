<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
| API dipakai untuk sinkronisasi POS, integrasi mobile, dan partner (PLAN 61).
| Modul internal tetap memakai Livewire.
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    foreach (glob(__DIR__.'/api/*.php') ?: [] as $moduleRoutes) {
        require $moduleRoutes;
    }
});
