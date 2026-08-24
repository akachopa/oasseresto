@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Proyeksi Kas">
    <x-page-header title="Proyeksi Kas"
                   subtitle="Kas saat ini ditambah piutang yang jatuh tempo, dikurangi hutang pada horizon yang sama." />

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        @foreach ($rows as $row)
            <x-kpi :label="$row['horizon'].' hari'"
                   :value="Money::compact($row['projected'])"
                   :hint="'Masuk '.Money::compact($row['inflows']).' · Keluar '.Money::compact($row['outflows'])"
                   :tone="$row['projected'] >= 0 ? 'positive' : 'negative'" />
        @endforeach
    </div>

    <div class="card card-pad mt-4">
        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Horizon</th>
                        <th class="text-right">Saldo kas</th>
                        <th class="text-right">Piutang masuk</th>
                        <th class="text-right">Hutang keluar</th>
                        <th class="text-right">Proyeksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['horizon'] }} hari</td>
                            <td class="text-right">{{ Money::rupiah($row['cash']) }}</td>
                            <td class="text-right">{{ Money::rupiah($row['inflows']) }}</td>
                            <td class="text-right">{{ Money::rupiah($row['outflows']) }}</td>
                            <td class="text-right font-medium">{{ Money::rupiah($row['projected']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
