@php
    use App\Modules\Core\Support\Money;
    use App\Modules\Core\Enums\AccountingPeriodStatus;
@endphp

<x-layouts.app title="Periode Akuntansi">
    <x-page-header title="Periode Akuntansi"
                   subtitle="Posting ditolak ke periode tertutup. Koreksi lewat jurnal reversal." />

    <form method="GET" class="card card-pad mb-4 flex items-end gap-3">
        <div>
            <label class="label" for="year">Tahun</label>
            <input id="year" name="year" type="number" class="input w-28" value="{{ $year }}">
        </div>
        <button class="btn-primary" type="submit">Tampilkan</button>
    </form>

    <div class="card overflow-hidden">
        <table class="table-oasse">
            <thead>
                <tr>
                    <th>Periode</th>
                    <th>Mulai</th>
                    <th>Selesai</th>
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($periods as $period)
                    <tr>
                        <td class="font-medium">{{ $period->label() }}</td>
                        <td>{{ $period->start_date->format('d/m/Y') }}</td>
                        <td>{{ $period->end_date->format('d/m/Y') }}</td>
                        <td><x-status-badge :status="$period->status" /></td>
                        <td>
                            <div class="flex justify-end gap-2">
                                @if ($period->allowsPosting())
                                    @can('accounting.period.close')
                                        <form method="POST" action="{{ route('accounting.periods.close', $period) }}"
                                              onsubmit="return confirm('Tutup periode {{ $period->label() }}?')">
                                            @csrf
                                            <button class="btn-secondary text-xs">Tutup</button>
                                        </form>
                                    @endcan
                                @else
                                    @can('accounting.period.reopen')
                                        <form method="POST" action="{{ route('accounting.periods.reopen', $period) }}"
                                              onsubmit="return confirm('Buka kembali periode {{ $period->label() }}?')">
                                            @csrf
                                            <button class="btn-secondary text-xs">Buka lagi</button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
