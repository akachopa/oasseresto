@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Laba Rugi">
    <x-page-header title="Laporan Laba Rugi"
                   :subtitle="'Laba bersih '.Money::rupiah($report['net_income'])" />

    @include('accounting.report.partials.date-range')

    @foreach ($report['groups'] as $group)
        <div class="card overflow-hidden mb-4">
            <div class="border-hairline flex items-center justify-between border-b px-4 py-3">
                <p class="font-medium">{{ $group['label'] }}</p>
                <p>{{ Money::rupiah($group['total']) }}</p>
            </div>
            <table class="table-oasse">
                <tbody>
                    @forelse ($group['rows']->filter(fn ($row) => abs($row->signed) >= 0.0001) as $row)
                        <tr>
                            <td class="font-mono text-xs w-24">{{ $row->code }}</td>
                            <td>{{ $row->name }}</td>
                            <td class="text-right">{{ Money::rupiah($row->signed) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-muted py-4 text-center">Tidak ada mutasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach

    <div class="card card-pad flex items-center justify-between font-semibold">
        <span>Laba (Rugi) Bersih</span>
        <span class="{{ $report['net_income'] >= 0 ? 'text-positive' : 'text-negative' }}">
            {{ Money::rupiah($report['net_income']) }}
        </span>
    </div>
</x-layouts.app>
