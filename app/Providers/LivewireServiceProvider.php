<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Komponen Livewire hidup di dalam modul, sehingga tidak terdeteksi oleh
 * auto-discovery Livewire yang hanya melihat app/Livewire. Provider ini
 * mendaftarkannya dengan nama <modul>.<komponen>, contoh product.product-index.
 */
class LivewireServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach (ModuleServiceProvider::MODULES as $module) {
            $path = app_path("Modules/{$module}/Livewire");

            if (! is_dir($path)) {
                continue;
            }

            foreach (Finder::create()->files()->in($path)->name('*.php') as $file) {
                $class = $this->classFromFile($module, $path, $file);

                if (! class_exists($class)) {
                    continue;
                }

                Livewire::component($this->componentName($module, $class), $class);
            }
        }
    }

    private function classFromFile(string $module, string $path, SplFileInfo $file): string
    {
        $relative = Str::after($file->getRealPath(), $path.DIRECTORY_SEPARATOR);
        $relative = str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relative);

        return "App\\Modules\\{$module}\\Livewire\\{$relative}";
    }

    private function componentName(string $module, string $class): string
    {
        $suffix = Str::after($class, "App\\Modules\\{$module}\\Livewire\\");

        $segments = array_map(
            fn (string $segment) => Str::kebab($segment),
            explode('\\', $suffix),
        );

        return Str::kebab($module).'.'.implode('.', $segments);
    }
}
