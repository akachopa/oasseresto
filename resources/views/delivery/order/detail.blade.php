@php
    use App\Modules\Core\Support\Money;

    $uninvoiced = $delivery->uninvoicedBaseQuantity();
@endphp

<x-layouts.app :title="'Surat Jalan '.$delivery->number">
    <x-page-header :title="$delivery->number"
                   :subtitle="$delivery->customer?->name.' · '.$delivery->warehouse?->name"
                   :back="route('delivery.orders.index')">
        <x-slot:actions>
            <x-status-badge :status="$delivery->status" />

            <a href="{{ route('delivery.orders.print', $delivery) }}" target="_blank" class="btn-secondary">
                <x-icon name="document" class="h-4 w-4" /> Cetak
            </a>

            @if ($delivery->isPickable())
                @can('inventory.pick')
                    <a href="{{ route('delivery.picking.pick', $delivery) }}" wire:navigate class="btn-primary">
                        <x-icon name="clipboard" class="h-4 w-4" /> Picking
                    </a>
                @endcan

                @can('delivery.create')
                    <a href="{{ route('delivery.orders.edit', $delivery) }}" wire:navigate class="btn-secondary">
                        <x-icon name="pencil" class="h-4 w-4" /> Ubah
                    </a>
                @endcan
            @endif

            @if ($delivery->isDispatchable())
                @can('delivery.dispatch')
                    <form method="POST" action="{{ route('delivery.orders.dispatch', $delivery) }}"
                          onsubmit="return confirm('Kirim barang dan kurangi stok gudang?')">
                        @csrf
                        <button type="submit" class="btn-primary">
                            <x-icon name="truck" class="h-4 w-4" /> Kirim Barang
                        </button>
                    </form>
                @endcan
            @endif

            @if ($delivery->isDelivered() && $uninvoiced > 0)
                @can('sales.create')
                    <a href="{{ route('sales.invoices.create', ['delivery' => $delivery->id]) }}" wire:navigate
                       class="btn-primary">
                        <x-icon name="receipt" class="h-4 w-4" /> Buat Invoice
                    </a>
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Tanggal Kirim" :value="$delivery->delivery_date->format('d/m/Y')"
               :hint="$delivery->order?->number ? 'SO '.$delivery->order->number : 'tanpa SO'" />
        <x-kpi label="Driver" :value="$delivery->driver_name ?: '-'"
               :hint="$delivery->vehicle_number" />
        <x-kpi label="Belum Ditagih" :value="Money::quantity($uninvoiced)"
               :tone="$uninvoiced > 0 ? 'caution' : 'positive'" hint="dalam satuan dasar" />

        @can('inventory.valuation.view')
            <x-kpi label="Nilai HPP" :value="Money::compact($delivery->total_value)" />
        @endcan
    </div>

    @if ($delivery->failure_reason)
        <div class="card card-pad border-l-negative mt-4 border-l-4">
            <p class="section-title">Gagal Kirim</p>
            <p class="mt-1 text-sm">{{ $delivery->failure_reason }}</p>
        </div>
    @endif

    <div class="card card-pad mt-4 space-y-3">
        <p class="section-title">Barang</p>

        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Batch</th>
                        <th class="text-right">Rencana</th>
                        <th class="text-right">Dipicking</th>
                        <th class="text-right">Ditagih</th>

                        @can('inventory.valuation.view')
                            <th class="text-right">HPP</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach ($delivery->items as $item)
                        <tr>
                            <td class="font-medium">
                                {{ $item->product?->name }}
                                <span class="text-muted block font-mono text-xs">{{ $item->product?->sku }}</span>
                            </td>
                            <td class="font-mono text-xs">{{ $item->batch?->batch_number ?: '-' }}</td>
                            <td class="text-right">
                                {{ Money::quantity($item->quantity) }} {{ $item->unit?->code }}
                                <span class="text-muted block text-xs">
                                    {{ Money::quantity($item->base_quantity) }} {{ $item->product?->baseUnit?->code }}
                                </span>
                            </td>
                            <td class="text-right">
                                {{ Money::quantity($item->picked_base_quantity) }}
                                @if ($item->isShort())
                                    <span class="badge-warning ml-1">kurang</span>
                                @endif
                            </td>
                            <td class="text-right">{{ Money::quantity($item->invoiced_base_quantity) }}</td>

                            @can('inventory.valuation.view')
                                <td class="text-right">{{ Money::rupiah($item->total_cost) }}</td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($delivery->note)
            <p class="text-muted border-hairline border-t pt-3 text-sm">{{ $delivery->note }}</p>
        @endif
    </div>

    @if ($delivery->isCompletable())
        <div class="card card-pad mt-4 grid gap-4 sm:grid-cols-2">
            @can('delivery.complete')
                <form method="POST" action="{{ route('delivery.orders.complete', $delivery) }}" class="space-y-2">
                    @csrf
                    <p class="section-title">Konfirmasi Diterima</p>

                    <div>
                        <label class="label" for="recipient_name">Nama Penerima</label>
                        <input id="recipient_name" name="recipient_name" class="input" placeholder="Nama penerima barang">
                    </div>

                    <button type="submit" class="btn-primary">
                        <x-icon name="check" class="h-4 w-4" /> Tandai Diterima
                    </button>
                </form>

                <form method="POST" action="{{ route('delivery.orders.fail', $delivery) }}" class="space-y-2">
                    @csrf
                    <p class="section-title">Gagal Kirim</p>

                    <div>
                        <label class="label" for="failure_reason">Alasan</label>
                        <input id="failure_reason" name="failure_reason" class="input" required
                               placeholder="Contoh: toko tutup, alamat tidak ditemukan">
                    </div>

                    <button type="submit" class="btn-danger">
                        <x-icon name="x" class="h-4 w-4" /> Tandai Gagal
                    </button>
                    <p class="text-muted text-xs">Barang dikembalikan ke stok dan sisa order dibuka kembali.</p>
                </form>
            @endcan
        </div>
    @endif

    <div class="card card-pad mt-4 space-y-2">
        <p class="section-title">Jejak Dokumen</p>

        <dl class="divide-hairline divide-y text-sm">
            @foreach ([
                'Sales Order' => $delivery->order?->number,
                'Dipicking' => $delivery->picked_at?->format('d/m/Y H:i'),
                'Petugas Picking' => $delivery->picker?->name,
                'Dikirim' => $delivery->dispatched_at?->format('d/m/Y H:i'),
                'Diterima' => $delivery->delivered_at?->format('d/m/Y H:i'),
                'Penerima' => $delivery->recipient_name,
            ] as $label => $value)
                <div class="flex items-start justify-between gap-3 py-2">
                    <dt class="text-muted">{{ $label }}</dt>
                    <dd class="text-right font-medium">{{ $value ?: '-' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</x-layouts.app>
