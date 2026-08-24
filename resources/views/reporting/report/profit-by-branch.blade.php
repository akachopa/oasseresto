@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Laba per Cabang">
    <x-page-header title="Laba per Cabang"
                   subtitle="Omzet dan laba kotor invoice yang sudah diposting." />

    @include('reporting.partials.date-range')

    <div class="card card-pad">
        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Cabang</th>
                        <th class="text-right">Dokumen</th>
                        <th class="text-right">Omzet</th>
                        <th class="text-right">HPP</th>
                        <th class="text-right">Laba</th>
                        <th class="text-right">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row->code }} · {{ $row->name }}</td>
                            <td class="text-right">{{ $row->documents }}</td>
                            <td class="text-right">{{ Money::rupiah($row->revenue) }}</td>
                            <td class="text-right">{{ Money::rupiah($row->cogs) }}</td>
                            <td class="text-right">{{ Money::rupiah($row->profit) }}</td>
                            <td class="text-right">{{ Money::percent($row->margin) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted py-6 text-center">Belum ada penjualan pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
