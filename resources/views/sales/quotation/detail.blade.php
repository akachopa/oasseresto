@php
    use App\Modules\Core\Enums\DocumentStatus;
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app :title="'Penawaran '.$quotation->number">
    <x-page-header :title="$quotation->number"
                   :subtitle="$quotation->customer?->name.' · '.$quotation->payment_term->label()"
                   :back="route('sales.quotations.index')">
        <x-slot:actions>
            <x-status-badge :status="$quotation->status" />

            @if ($quotation->status->isEditable())
                @can('sales.edit')
                    <a href="{{ route('sales.quotations.edit', $quotation) }}" wire:navigate class="btn-secondary">
                        <x-icon name="pencil" class="h-4 w-4" /> Ubah
                    </a>
                @endcan

                @can('sales.create')
                    <form method="POST" action="{{ route('sales.quotations.send', $quotation) }}">
                        @csrf
                        <button type="submit" class="btn-primary">
                            <x-icon name="check" class="h-4 w-4" /> Tandai Terkirim
                        </button>
                    </form>
                @endcan
            @endif

            @if ($quotation->status === DocumentStatus::Submitted)
                @can('sales.create')
                    <form method="POST" action="{{ route('sales.quotations.accept', $quotation) }}">
                        @csrf
                        <button type="submit" class="btn-secondary">
                            <x-icon name="check" class="h-4 w-4" /> Diterima Customer
                        </button>
                    </form>
                @endcan
            @endif

            @if ($quotation->isConvertible())
                @can('sales.create')
                    <form method="POST" action="{{ route('sales.orders.from-quotation', $quotation) }}">
                        @csrf
                        <button type="submit" class="btn-primary">
                            <x-icon name="receipt" class="h-4 w-4" /> Jadikan Sales Order
                        </button>
                    </form>
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Tanggal" :value="$quotation->quotation_date->format('d/m/Y')"
               :hint="'Dibuat '.($quotation->creator?->name ?? '-')" />
        <x-kpi label="Berlaku Sampai" :value="$quotation->valid_until?->format('d/m/Y') ?? '-'"
               :tone="$quotation->isExpired() ? 'negative' : 'neutral'"
               :hint="$quotation->isExpired() ? 'sudah kedaluwarsa' : null" />
        <x-kpi label="Total" :value="Money::compact($quotation->total)" />
        <x-kpi label="Salesman" :value="$quotation->salesman?->name ?? '-'" />
    </div>

    <div class="card card-pad mt-4 space-y-3">
        <p class="section-title">Barang Ditawarkan</p>

        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th class="text-right">Kuantitas</th>
                        <th class="text-right">Harga List</th>
                        <th class="text-right">Harga Tawar</th>
                        <th class="text-right">Diskon</th>
                        <th class="text-right">Pajak</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($quotation->items as $item)
                        <tr>
                            <td class="font-medium">
                                {{ $item->product?->name }}
                                <span class="text-muted block font-mono text-xs">{{ $item->product?->sku }}</span>
                            </td>
                            <td class="text-right">
                                {{ Money::quantity($item->quantity) }} {{ $item->unit?->code }}
                            </td>
                            <td class="text-right">{{ Money::rupiah($item->list_price) }}</td>
                            <td class="text-right">{{ Money::rupiah($item->unit_price) }}</td>
                            <td class="text-right">{{ Money::rupiah($item->discount_amount) }}</td>
                            <td class="text-right">{{ Money::rupiah($item->tax_amount) }}</td>
                            <td class="text-right font-medium">{{ Money::rupiah($item->line_total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <dl class="border-hairline ml-auto w-full max-w-xs space-y-1 border-t pt-3 text-sm sm:w-72">
            <div class="flex items-center justify-between">
                <dt class="text-muted">Subtotal</dt>
                <dd>{{ Money::rupiah($quotation->subtotal) }}</dd>
            </div>
            <div class="flex items-center justify-between">
                <dt class="text-muted">Pajak</dt>
                <dd>{{ Money::rupiah($quotation->tax_amount) }}</dd>
            </div>
            <div class="border-hairline flex items-center justify-between border-t pt-1 text-base font-semibold">
                <dt>Total</dt>
                <dd>{{ Money::rupiah($quotation->total) }}</dd>
            </div>
        </dl>

        @if ($quotation->note || $quotation->terms)
            <p class="text-muted border-hairline border-t pt-3 text-sm">
                {{ $quotation->note }}
                @if ($quotation->terms)
                    <span class="block">Syarat: {{ $quotation->terms }}</span>
                @endif
            </p>
        @endif
    </div>

    @if ($quotation->orders->isNotEmpty())
        <div class="card card-pad mt-4 space-y-3">
            <p class="section-title">Sales Order Terkait</p>

            <div class="oasse-table-wrap">
                <table class="table-oasse">
                    <thead>
                        <tr>
                            <th>Nomor</th>
                            <th>Tanggal</th>
                            <th class="text-right">Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($quotation->orders as $order)
                            <tr>
                                <td>
                                    <a href="{{ route('sales.orders.detail', $order) }}" wire:navigate
                                       class="font-mono text-xs hover:text-brand-600">{{ $order->number }}</a>
                                </td>
                                <td>{{ $order->order_date->format('d/m/Y') }}</td>
                                <td class="text-right">{{ Money::rupiah($order->total) }}</td>
                                <td><x-status-badge :status="$order->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card card-pad mt-4 space-y-2">
        <p class="section-title">Jejak Dokumen</p>

        <dl class="divide-hairline divide-y text-sm">
            @foreach ([
                'Dikirim' => $quotation->sent_at?->format('d/m/Y H:i'),
                'Diterima Customer' => $quotation->accepted_at?->format('d/m/Y H:i'),
                'Gudang Rujukan' => $quotation->warehouse?->name,
            ] as $label => $value)
                <div class="flex items-start justify-between gap-3 py-2">
                    <dt class="text-muted">{{ $label }}</dt>
                    <dd class="text-right font-medium">{{ $value ?: '-' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</x-layouts.app>
