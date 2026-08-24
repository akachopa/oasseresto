<x-layouts.app title="Lainnya">
    <x-page-header title="Lainnya" subtitle="Seluruh menu yang tersedia untuk akun Anda." />

    <div class="space-y-4">
        @foreach (\App\Modules\Core\Support\Navigation::sidebar(auth()->user()) as $group)
            <div class="card overflow-hidden">
                <div class="border-hairline flex items-center gap-2 border-b px-4 py-2.5">
                    <x-icon :name="$group['icon']" class="h-4 w-4 text-brand-500" />
                    <span class="section-title">{{ $group['label'] }}</span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3">
                    @foreach ($group['items'] as $item)
                        <a href="{{ route($item['route']) }}" wire:navigate
                           class="border-hairline hover:bg-panel-soft border-r border-b px-4 py-3 text-sm transition">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.app>
