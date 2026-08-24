@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Neraca">
    <x-page-header title="Neraca"
                   :subtitle="'Posisi per '.$asOf->format('d/m/Y').($report['balanced'] ? ' · Seimbang' : ' · Belum seimbang')" />

    <form method="GET" class="card card-pad mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="label" for="as_of">Per tanggal</label>
            <input id="as_of" name="as_of" type="date" class="input" value="{{ $asOf->toDateString() }}">
        </div>
        <button class="btn-primary" type="submit">Tampilkan</button>
    </form>

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($report['groups'] as $key => $group)
            <div class="card overflow-hidden">
                <div class="border-hairline flex items-center justify-between border-b px-4 py-3">
                    <p class="font-medium">{{ $group['label'] }}</p>
                    <p>{{ Money::rupiah($group['total']) }}</p>
                </div>
                <table class="table-oasse">
                    <tbody>
                        @foreach ($group['rows']->filter(fn ($row) => abs($row->signed) >= 0.0001) as $row)
                            <tr>
                                <td class="font-mono text-xs w-24">{{ $row->code }}</td>
                                <td>{{ $row->name }}</td>
                                <td class="text-right">{{ Money::rupiah($row->signed) }}</td>
                            </tr>
                        @endforeach
                        @if ($key === 'equity' && abs($report['net_income']) >= 0.0001)
                            <tr>
                                <td class="font-mono text-xs">3300</td>
                                <td>Laba Tahun Berjalan</td>
                                <td class="text-right">{{ Money::rupiah($report['net_income']) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
</x-layouts.app>
