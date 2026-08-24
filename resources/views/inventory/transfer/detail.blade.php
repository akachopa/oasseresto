@php
    use App\Modules\Core\Support\Money;

    $outstanding = $transfer->items->sum(fn ($item) => $item->outstandingBaseQuantity());
@endphp

<x-layouts.app :title="'Transfer '.$transfer->number">
    <x-page-header :title="$transfer->number"
                   :subtitle="$transfer->fromWarehouse?->name.' → '.$transfer->toWarehouse?->name"
                   :back="route('inventory.transfers.index')">
        <x-slot:actions>
            <x-status-badge :status="$transfer->status" />

            @if ($transfer->status->isEditable())
                <a href="{{ route('inventory.transfers.edit', $transfer) }}" wire:navigate class="btn-secondary">
                    <x-icon name="pencil" class="h-4 w-4" /> Ubah
                </a>

                <form method="POST" action="{{ route('inventory.transfers.ship', $transfer) }}"
                      onsubmit="return confirm('Kirim barang dan kurangi stok gudang asal?')">
                    @csrf
                    <button type="submit" class="btn-primary">
                        <x-icon name="truck" class="h-4 w-4" /> Kirim Barang
                    </button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Tanggal" :value="$transfer->transfer_date->format('d/m/Y')"
               :hint="'Dibuat '.($transfer->creator?->name ?? '-')" />
        <x-kpi label="Jumlah Baris" :value="(string) $transfer->items->count()" />
        <x-kpi label="Belum Diterima" :value="Money::quantity($outstanding)"
               :tone="$outstanding > 0 ? 'caution' : 'positive'"
               hint="dalam satuan dasar" />

        @can('inventory.valuation.view')
            <x-kpi label="Nilai Transfer" :value="Money::compact($transfer->total_value)" />
        @endcan
    </div>

    <form method="POST" action="{{ route('inventory.transfers.receive', $transfer) }}" class="mt-4 space-y-4">
        @csrf

        <div class="card card-pad space-y-3">
            <p class="section-title">Barang</p>

            <div class="oasse-table-wrap">
                <table class="table-oasse">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Batch</th>
                            <th class="text-right">Kuantitas</th>
                            <th class="text-right">Base</th>
                            <th class="text-right">Diterima</th>
                            <th class="text-right">HPP</th>
                            @if ($transfer->isInTransit())
                                <th class="text-right">Terima Sekarang</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transfer->items as $item)
                            <tr>
                                <td class="font-medium">
                                    {{ $item->product?->name }}
                                    <span class="text-muted block font-mono text-xs">{{ $item->product?->sku }}</span>
                                </td>
                                <td class="font-mono text-xs">{{ $item->batch?->batch_number ?: '-' }}</td>
                                <td class="text-right">
                                    {{ Money::quantity($item->quantity) }} {{ $item->unit?->code }}
                                </td>
                                <td class="text-right">
                                    {{ Money::quantity($item->base_quantity) }} {{ $item->product?->baseUnit?->code }}
                                </td>
                                <td class="text-right">
                                    {{ Money::quantity($item->received_base_quantity) }}
                                    @if ($item->outstandingBaseQuantity() > 0 && ! $transfer->status->isEditable())
                                        <span class="badge-warning ml-1">
                                            sisa {{ Money::quantity($item->outstandingBaseQuantity()) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-right">{{ Money::rupiah($item->unit_cost) }}</td>
                                @if ($transfer->isInTransit())
                                    <td class="text-right">
                                        <input type="number" step="0.0001" min="0"
                                               max="{{ $item->outstandingBaseQuantity() }}"
                                               name="received[{{ $item->id }}]"
                                               value="{{ $item->outstandingBaseQuantity() }}"
                                               class="input w-28 text-right">
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($transfer->note)
                <p class="text-muted border-hairline border-t pt-3 text-sm">{{ $transfer->note }}</p>
            @endif
        </div>

        @if ($transfer->isInTransit())
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="btn-primary">
                    <x-icon name="check" class="h-4 w-4" /> Catat Penerimaan
                </button>
                <p class="text-muted text-xs">
                    Kuantitas boleh dikurangi bila barang datang bertahap; sisanya tetap dalam perjalanan.
                </p>
            </div>
        @endif
    </form>

    <div class="card card-pad mt-4 space-y-2">
        <p class="section-title">Jejak Dokumen</p>

        <dl class="divide-hairline divide-y text-sm">
            @foreach ([
                'Dikirim' => $transfer->shipped_at?->format('d/m/Y H:i'),
                'Diterima' => $transfer->received_at?->format('d/m/Y H:i'),
                'Cabang Asal' => $transfer->fromBranch?->name,
                'Cabang Tujuan' => $transfer->toBranch?->name,
            ] as $label => $value)
                <div class="flex items-start justify-between gap-3 py-2">
                    <dt class="text-muted">{{ $label }}</dt>
                    <dd class="text-right font-medium">{{ $value ?: '-' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</x-layouts.app>
