@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Ringkasan Bisnis">
    <x-page-header title="Ringkasan Bisnis"
                   subtitle="KPI dari agregasi harian, bukan query transaksi mentah." />

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-kpi label="Omzet Hari Ini" :value="Money::compact($today->sales_amount)" />
        <x-kpi label="Omzet Bulan Ini" :value="Money::compact($month->sales_amount)" />
        <x-kpi label="Laba Kotor MTD" :value="Money::compact($month->gross_profit)"
               :tone="$month->gross_profit >= 0 ? 'positive' : 'negative'" />
        <x-kpi label="Saldo Kas" :value="Money::compact($today->cash_balance)" />
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div class="card card-pad">
            <p class="section-title mb-3">Proyeksi kas</p>
            <div class="oasse-table-wrap">
                <table class="table-oasse">
                    <thead>
                        <tr>
                            <th>Horizon</th>
                            <th class="text-right">Masuk</th>
                            <th class="text-right">Keluar</th>
                            <th class="text-right">Proyeksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($forecast as $row)
                            <tr>
                                <td>{{ $row['horizon'] }} hari</td>
                                <td class="text-right">{{ Money::rupiah($row['inflows']) }}</td>
                                <td class="text-right">{{ Money::rupiah($row['outflows']) }}</td>
                                <td class="text-right font-medium">{{ Money::rupiah($row['projected']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-pad">
            <p class="section-title mb-3">Omzet 14 hari</p>
            <ul class="space-y-1.5">
                @forelse (array_reverse($trend) as $point)
                    <li class="flex items-center justify-between text-sm">
                        <span class="text-muted">{{ \Illuminate\Support\Carbon::parse($point['date'])->format('d/m') }}</span>
                        <span class="tabular-nums">{{ Money::compact($point['sales_amount']) }}</span>
                    </li>
                @empty
                    <li class="text-muted text-sm">Belum ada data agregat.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-layouts.app>
