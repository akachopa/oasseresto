@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app :title="'Penyesuaian '.$adjustment->number">
    <x-page-header :title="$adjustment->number"
                   :subtitle="$adjustment->warehouse?->name.' · '.$adjustment->reasonLabel()"
                   :back="route('inventory.adjustments.index')">
        <x-slot:actions>
            <x-status-badge :status="$adjustment->status" />

            @if ($adjustment->status->isEditable())
                <a href="{{ route('inventory.adjustments.edit', $adjustment) }}" wire:navigate class="btn-secondary">
                    <x-icon name="pencil" class="h-4 w-4" /> Ubah
                </a>

                <form method="POST" action="{{ route('inventory.adjustments.post', $adjustment) }}"
                      onsubmit="return confirm('Posting penyesuaian ini ke kartu stok?')">
                    @csrf
                    <button type="submit" class="btn-primary">
                        <x-icon name="check" class="h-4 w-4" /> Posting
                    </button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Tanggal" :value="$adjustment->adjustment_date->format('d/m/Y')"
               :hint="'Dibuat '.($adjustment->creator?->name ?? '-')" />
        <x-kpi label="Jumlah Baris" :value="(string) $adjustment->items->count()" />
        <x-kpi label="Alasan" :value="$adjustment->reasonLabel()" />

        @can('inventory.valuation.view')
            <x-kpi label="Nilai Penyesuaian" :value="Money::compact($adjustment->total_value)"
                   :tone="$adjustment->total_value < 0 ? 'negative' : 'positive'" />
        @endcan
    </div>

    <div class="card card-pad mt-4 space-y-3">
        <p class="section-title">Barang</p>

        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Batch</th>
                        <th class="text-right">Kuantitas</th>
                        <th class="text-right">Base</th>
                        <th class="text-right">HPP</th>
                        <th class="text-right">Nilai</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($adjustment->items as $item)
                        <tr>
                            <td class="font-medium">
                                {{ $item->product?->name }}
                                <span class="text-muted block font-mono text-xs">{{ $item->product?->sku }}</span>
                            </td>
                            <td class="font-mono text-xs">{{ $item->batch?->batch_number ?: '-' }}</td>
                            <td class="text-right {{ $item->quantity < 0 ? 'text-negative' : 'text-positive' }}">
                                {{ Money::quantity($item->quantity) }} {{ $item->unit?->code }}
                            </td>
                            <td class="text-right">{{ Money::quantity($item->base_quantity) }}</td>
                            <td class="text-right">{{ Money::rupiah($item->unit_cost) }}</td>
                            <td class="text-right">{{ Money::rupiah($item->total_cost) }}</td>
                            <td class="text-muted text-xs">{{ $item->note ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($adjustment->note)
            <p class="text-muted border-hairline border-t pt-3 text-sm">{{ $adjustment->note }}</p>
        @endif
    </div>
</x-layouts.app>
