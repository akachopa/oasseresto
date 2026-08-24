@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Laporan Penjualan">
    <x-page-header title="Laporan Penjualan"
                   subtitle="Invoice yang sudah diposting pada periode terpilih." />

    @include('reporting.partials.date-range')

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <x-kpi label="Omzet" :value="Money::compact($total)" />
        <x-kpi label="HPP" :value="Money::compact($cogs)" />
        <x-kpi label="Laba Kotor" :value="Money::compact($total - $cogs)"
               :tone="($total - $cogs) >= 0 ? 'positive' : 'negative'" />
    </div>

    <div class="card card-pad mt-4">
        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Tanggal</th>
                        <th>Customer</th>
                        <th class="text-right">Nilai</th>
                        <th class="text-right">Laba</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="font-mono text-xs">
                                <a href="{{ route('sales.invoices.detail', $row) }}" wire:navigate class="text-brand-600">
                                    {{ $row->number }}
                                </a>
                            </td>
                            <td>{{ $row->invoice_date->format('d/m/Y') }}</td>
                            <td>{{ $row->customer?->name }}</td>
                            <td class="text-right">{{ Money::rupiah($row->total) }}</td>
                            <td class="text-right">{{ Money::rupiah($row->grossProfit()) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-muted py-6 text-center">Tidak ada invoice pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
