@php
    use App\Modules\Core\Support\Money;
@endphp

<div class="space-y-4">
    <div class="card card-pad space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="section-title">Progres Hitung</p>
                <p class="text-muted text-sm">{{ $countedCount }} dari {{ $totalCount }} baris sudah dihitung.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="saveCounts" wire:loading.attr="disabled" class="btn-secondary">
                    Simpan Hitungan
                </button>
                <button type="button" wire:click="post" wire:loading.attr="disabled" class="btn-primary">
                    <span wire:loading.remove wire:target="post">Simpan & Posting</span>
                    <span wire:loading wire:target="post">Memproses...</span>
                </button>
            </div>
        </div>

        <div class="bg-panel-soft h-2 overflow-hidden rounded-full">
            <div class="bg-brand-500 h-full rounded-full transition-all"
                 style="width: {{ $totalCount > 0 ? round($countedCount / $totalCount * 100) : 0 }}%"></div>
        </div>

        @error('counts') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div class="card card-pad space-y-3">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label class="label" for="opname-search">Cari Produk</label>
                <input id="opname-search" wire:model.live.debounce.300ms="search" class="input"
                       placeholder="Nama atau SKU">
            </div>

            <div>
                <label class="label" for="opname-filter">Tampilkan</label>
                <select id="opname-filter" wire:model.live="filter" class="input">
                    <option value="all">Semua Baris</option>
                    <option value="uncounted">Belum Dihitung</option>
                    <option value="difference">Ada Selisih</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Tampilan kartu dipakai agar nyaman dihitung dari ponsel di gudang. --}}
    <div class="space-y-2">
        @forelse ($items as $item)
            @php
                $entered = $counts[$item->id] ?? null;
                $difference = ($entered === null || $entered === '')
                    ? null
                    : round((float) $entered - $item->system_base_quantity, 4);
            @endphp

            <div class="card card-pad grid grid-cols-1 items-end gap-3 sm:grid-cols-12"
                 wire:key="opname-item-{{ $item->id }}">
                <div class="sm:col-span-5">
                    <p class="font-medium">{{ $item->product?->name }}</p>
                    <p class="text-muted font-mono text-xs">
                        {{ $item->product?->sku }}
                        @if ($item->batch)
                            · batch {{ $item->batch->batch_number }}
                        @endif
                    </p>
                </div>

                <div class="sm:col-span-2">
                    <p class="label">Saldo Sistem</p>
                    <p class="font-semibold tabular-nums">
                        {{ Money::quantity($item->system_base_quantity) }}
                        <span class="text-muted text-xs">{{ $item->product?->baseUnit?->code }}</span>
                    </p>
                </div>

                <div class="sm:col-span-3">
                    <label class="label" for="count-{{ $item->id }}">Hitung Fisik</label>
                    <input id="count-{{ $item->id }}" type="number" step="0.0001" min="0" inputmode="decimal"
                           wire:model.live.debounce.500ms="counts.{{ $item->id }}" class="input text-right">

                    @if ($difference !== null && $difference !== 0.0)
                        <p class="mt-1 text-xs {{ $difference < 0 ? 'text-negative' : 'text-positive' }}">
                            Selisih {{ $difference > 0 ? '+' : '' }}{{ Money::quantity($difference) }}
                        </p>
                    @elseif ($difference === 0.0)
                        <p class="text-positive mt-1 text-xs">Cocok dengan sistem</p>
                    @endif
                </div>

                <div class="sm:col-span-2">
                    <button type="button" wire:click="acceptSystem({{ $item->id }})" class="btn-secondary w-full">
                        Sesuai Sistem
                    </button>
                </div>
            </div>
        @empty
            <x-empty-state title="Tidak ada baris"
                           message="Ubah filter atau kata kunci pencarian untuk melihat baris lain." />
        @endforelse
    </div>
</div>
