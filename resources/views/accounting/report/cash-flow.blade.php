@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Arus Kas">
    <x-page-header title="Laporan Arus Kas"
                   subtitle="Dikelompokkan dari mutasi akun kas dan bank pada jurnal." />

    @include('accounting.report.partials.date-range')

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-4">
        <x-kpi label="Operasi" :value="Money::compact($report['operating'])" />
        <x-kpi label="Investasi" :value="Money::compact($report['investing'])" />
        <x-kpi label="Pendanaan" :value="Money::compact($report['financing'])" />
        <x-kpi label="Bersih" :value="Money::compact($report['net'])"
               :tone="$report['net'] >= 0 ? 'positive' : 'negative'" />
    </div>

    <div class="card overflow-hidden">
        <table class="table-oasse">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Nomor</th>
                    <th>Keterangan</th>
                    <th>Kelompok</th>
                    <th class="text-right">Nilai</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['rows'] as $row)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($row->date)->format('d/m/Y') }}</td>
                        <td class="font-mono text-xs">{{ $row->number }}</td>
                        <td>{{ $row->description }}</td>
                        <td>{{ $row->section }}</td>
                        <td class="text-right {{ $row->amount >= 0 ? 'text-positive' : 'text-negative' }}">
                            {{ Money::rupiah($row->amount) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-muted py-6 text-center">Belum ada mutasi kas di periode ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
