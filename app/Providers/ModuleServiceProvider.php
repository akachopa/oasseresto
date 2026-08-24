<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Core\Services\ScopeManager;
use Illuminate\Support\ServiceProvider;

/**
 * Titik registrasi tunggal untuk seluruh modul (PLAN 64). Setiap modul cukup
 * menaruh migration di app/Modules/<Nama>/Database/migrations dan komponen
 * Livewire di app/Modules/<Nama>/Livewire.
 */
class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Urutan daftar ini menentukan urutan registrasi, bukan urutan migrasi.
     * Urutan migrasi tetap ditentukan oleh timestamp nama file.
     */
    public const MODULES = [
        'Core',
        'Auth',
        'Company',
        'Product',
        'Customer',
        'Supplier',
        'Purchase',
        'Inventory',
        'Sales',
        'Delivery',
        'Finance',
        'Payroll',
        'Asset',
        'Accounting',
        'Reporting',
        'Approval',
        'Notification',
    ];

    public function register(): void
    {
        $this->app->singleton(ScopeManager::class);
    }

    public function boot(): void
    {
        foreach (self::MODULES as $module) {
            $path = app_path("Modules/{$module}/Database/migrations");

            if (is_dir($path)) {
                $this->loadMigrationsFrom($path);
            }
        }
    }
}
