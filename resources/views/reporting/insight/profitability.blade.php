@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Profitabilitas">
    <x-page-header title="Profitabilitas"
                   subtitle="Laba kotor dari invoice yang sudah diposting, dipecah per produk dan cabang." />

    @include('reporting.partials.date-range')

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="card card-pad">
            <p class="section-title mb-3">Per cabang</p>
            <div class="oasse-table-wrap">
                <table class="table-oasse">
                    <thead>
                        <tr>
                            <th>Cabang</th>
                            <th class="text-right">Omzet</th>
                            <th class="text-right">Laba</th>
                            <th class="text-right">Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($branches as $row)
                            <tr>
                                <td>{{ $row->code }} · {{ $row->name }}</td>
                                <td class="text-right">{{ Money::rupiah($row->revenue) }}</td>
                                <td class="text-right">{{ Money::rupiah($row->profit) }}</td>
                                <td class="text-right">{{ Money::percent($row->margin) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-muted py-6 text-center">Belum ada penjualan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-pad">
            <p class="section-title mb-3">Per produk</p>
            <div class="oasse-table-wrap">
                <table class="table-oasse">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Produk</th>
                            <th class="text-right">Omzet</th>
                            <th class="text-right">Laba</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $row)
                            <tr>
                                <td class="font-mono text-xs">{{ $row->sku }}</td>
                                <td>{{ $row->name }}</td>
                                <td class="text-right">{{ Money::rupiah($row->revenue) }}</td>
                                <td class="text-right">{{ Money::rupiah($row->profit) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-muted py-6 text-center">Belum ada penjualan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
