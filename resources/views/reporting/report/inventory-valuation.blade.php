@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Nilai Persediaan">
    <x-page-header title="Nilai Persediaan"
                   subtitle="Saldo stok dikalikan harga pokok rata-rata per gudang." />

    <x-kpi label="Total Nilai" :value="Money::compact($total)" />

    <div class="card card-pad mt-4">
        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Produk</th>
                        <th>Gudang</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">HPP</th>
                        <th class="text-right">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="font-mono text-xs">{{ $row->sku }}</td>
                            <td>{{ $row->product_name }}</td>
                            <td>{{ $row->warehouse_name }}</td>
                            <td class="text-right">{{ Money::quantity($row->quantity) }}</td>
                            <td class="text-right">{{ Money::rupiah($row->average_cost) }}</td>
                            <td class="text-right">{{ Money::rupiah($row->total_value) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted py-6 text-center">Tidak ada saldo stok bernilai.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
