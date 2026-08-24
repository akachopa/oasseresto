<div x-data
     x-on:keydown.window.prevent.cmd.k="$dispatch('open-global-search')"
     x-on:keydown.window.prevent.ctrl.k="$dispatch('open-global-search')">
    @if ($open)
        <div class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-24" x-on:keydown.escape="$wire.close()">
            <div class="absolute inset-0 bg-black/50" wire:click="close"></div>

            <div class="card relative w-full max-w-xl overflow-hidden shadow-2xl">
                <div class="border-hairline flex items-center gap-2 border-b px-4">
                    <x-icon name="search" class="h-4 w-4 text-muted" />
                    <input type="text" wire:model.live.debounce.300ms="term" autofocus
                           placeholder="Cari produk, customer, supplier, invoice, PO..."
                           class="w-full bg-transparent py-3.5 text-sm focus:outline-none">
                    <button type="button" wire:click="close" class="btn-icon text-muted"><x-icon name="x" class="h-4 w-4" /></button>
                </div>

                <div class="max-h-80 overflow-y-auto">
                    @forelse ($groups as $group)
                        <div class="px-2 py-1.5">
                            <p class="px-2 py-1 text-[10px] font-semibold tracking-wide text-muted uppercase">{{ $group['label'] }}</p>
                            @foreach ($group['items'] as $item)
                                <a @if ($item['url']) href="{{ $item['url'] }}" wire:navigate @endif
                                   class="hover:bg-panel-soft flex items-center justify-between rounded-lg px-2 py-2 text-sm">
                                    <span class="truncate">{{ $item['title'] }}</span>
                                    <span class="ml-3 shrink-0 text-xs text-muted">{{ $item['subtitle'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-muted">
                            {{ strlen($term) >= 2 ? 'Tidak ada hasil.' : 'Ketik minimal 2 karakter.' }}
                        </p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
