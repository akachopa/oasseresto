@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Laba per Produk">
    <x-page-header title="Laba per Produk"
                   subtitle="Omzet dan laba kotor dari baris invoice yang sudah diposting." />

    @include('reporting.partials.date-range')

    <div class="card card-pad">
        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Produk</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Omzet</th>
                        <th class="text-right">HPP</th>
                        <th class="text-right">Laba</th>
                        <th class="text-right">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="font-mono text-xs">{{ $row->sku }}</td>
                            <td>{{ $row->name }}</td>
                            <td class="text-right">{{ Money::quantity($row->quantity) }}</td>
                            <td class="text-right">{{ Money::rupiah($row->revenue) }}</td>
                            <td class="text-right">{{ Money::rupiah($row->cogs) }}</td>
                            <td class="text-right">{{ Money::rupiah($row->profit) }}</td>
                            <td class="text-right">{{ Money::percent($row->margin) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-muted py-6 text-center">Belum ada penjualan pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
