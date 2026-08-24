@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Laporan Pembelian">
    <x-page-header title="Laporan Pembelian"
                   subtitle="Invoice pembelian yang sudah diposting pada periode terpilih.">
        <x-slot:actions>
            <x-export-buttons type="purchases" permission="report.purchase"
                              :from="$from->toDateString()" :to="$to->toDateString()" />
        </x-slot:actions>
    </x-page-header>

    @include('reporting.partials.date-range')

    <x-kpi label="Total Pembelian" :value="Money::compact($total)" class="mb-4" />

    <div class="card card-pad mt-4">
        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Tanggal</th>
                        <th>Supplier</th>
                        <th class="text-right">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="font-mono text-xs">
                                <a href="{{ route('purchase.invoices.detail', $row) }}" wire:navigate class="text-brand-600">
                                    {{ $row->number }}
                                </a>
                            </td>
                            <td>{{ $row->invoice_date->format('d/m/Y') }}</td>
                            <td>{{ $row->supplier?->name }}</td>
                            <td class="text-right">{{ Money::rupiah($row->total) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-muted py-6 text-center">Tidak ada invoice pembelian pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
