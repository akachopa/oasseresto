@php
    use App\Modules\Core\Support\Money;

    $items = $opname->items;
@endphp

<x-layouts.app :title="'Opname '.$opname->number">
    <x-page-header :title="$opname->number"
                   :subtitle="$opname->warehouse?->name.' · '.($opname->scope_type === 'category' ? 'Kategori '.$opname->category?->name : 'Seluruh gudang')"
                   :back="route('inventory.opnames.index')">
        <x-slot:actions>
            <x-status-badge :status="$opname->status" />

            @if ($opname->isCountable())
                <a href="{{ route('inventory.opnames.count', $opname) }}" wire:navigate class="btn-secondary">
                    <x-icon name="clipboard" class="h-4 w-4" /> Lanjut Hitung
                </a>

                <form method="POST" action="{{ route('inventory.opnames.post', $opname) }}"
                      onsubmit="return confirm('Posting opname dan tulis selisihnya ke kartu stok?')">
                    @csrf
                    <button type="submit" class="btn-primary">
                        <x-icon name="check" class="h-4 w-4" /> Posting
                    </button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Tanggal" :value="$opname->opname_date->format('d/m/Y')"
               :hint="'Petugas '.($opname->counter?->name ?? '-')" />
        <x-kpi label="Baris Dihitung"
               :value="$items->where('is_counted', true)->count().' / '.$items->count()" />
        <x-kpi label="Baris Selisih" :value="(string) $opname->difference_count"
               :tone="$opname->difference_count > 0 ? 'caution' : 'positive'" />

        @can('inventory.valuation.view')
            <x-kpi label="Nilai Selisih" :value="Money::compact($opname->difference_value)"
                   :tone="$opname->difference_value < 0 ? 'negative' : 'positive'" />
        @endcan
    </div>

    <div class="card card-pad mt-4 space-y-3">
        <p class="section-title">Hasil Hitung</p>

        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Batch</th>
                        <th class="text-right">Sistem</th>
                        <th class="text-right">Fisik</th>
                        <th class="text-right">Selisih</th>
                        <th class="text-right">Nilai</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td class="font-medium">
                                {{ $item->product?->name }}
                                <span class="text-muted block font-mono text-xs">{{ $item->product?->sku }}</span>
                            </td>
                            <td class="font-mono text-xs">{{ $item->batch?->batch_number ?: '-' }}</td>
                            <td class="text-right">{{ Money::quantity($item->system_base_quantity) }}</td>
                            <td class="text-right">
                                @if ($item->is_counted)
                                    {{ Money::quantity($item->counted_base_quantity) }}
                                @else
                                    <span class="badge-warning">Belum dihitung</span>
                                @endif
                            </td>
                            <td class="text-right {{ $item->difference_base_quantity < 0 ? 'text-negative' : ($item->difference_base_quantity > 0 ? 'text-positive' : '') }}">
                                {{ $item->difference_base_quantity > 0 ? '+' : '' }}{{ Money::quantity($item->difference_base_quantity) }}
                            </td>
                            <td class="text-right">{{ Money::rupiah($item->difference_value) }}</td>
                            <td class="text-muted text-xs">{{ $item->note ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted">Tidak ada barang dalam cakupan opname ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($opname->note)
            <p class="text-muted border-hairline border-t pt-3 text-sm">{{ $opname->note }}</p>
        @endif
    </div>
</x-layouts.app>
