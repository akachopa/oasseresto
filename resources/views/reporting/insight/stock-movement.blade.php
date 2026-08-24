@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Pergerakan Stok">
    <x-page-header title="Pergerakan Stok"
                   subtitle="Ringkasan kartu stok per tanggal dan jenis transaksi." />

    @include('reporting.partials.date-range')

    <div class="card card-pad">
        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jenis</th>
                        <th class="text-right">Kuantitas dasar</th>
                        <th class="text-right">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($row->transaction_date)->format('d/m/Y') }}</td>
                            <td>{{ $row->type_label }}</td>
                            <td class="text-right">{{ Money::quantity($row->quantity) }}</td>
                            <td class="text-right">{{ Money::rupiah($row->value) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-muted py-6 text-center">Tidak ada pergerakan pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
