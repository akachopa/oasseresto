@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Buku Besar">
    <x-page-header title="Buku Besar" subtitle="Mutasi per akun dari jurnal yang sudah diposting." />

    <form method="GET" class="card card-pad mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="label" for="account">Akun</label>
            <select id="account" name="account" class="input w-auto min-w-64">
                @foreach ($accounts as $item)
                    <option value="{{ $item->id }}" @selected($account?->id === $item->id)>{{ $item->label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="from">Dari</label>
            <input id="from" name="from" type="date" class="input" value="{{ $from->toDateString() }}">
        </div>
        <div>
            <label class="label" for="to">Sampai</label>
            <input id="to" name="to" type="date" class="input" value="{{ $to->toDateString() }}">
        </div>
        <button class="btn-primary" type="submit">Tampilkan</button>
    </form>

    @if ($account)
        <div class="card overflow-hidden">
            <div class="border-hairline flex items-center justify-between border-b px-4 py-3">
                <p class="font-medium">{{ $account->label }}</p>
                <p class="text-sm text-muted">Saldo awal {{ Money::rupiah($ledger['opening']) }}</p>
            </div>
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Nomor</th>
                        <th>Keterangan</th>
                        <th class="text-right">Debit</th>
                        <th class="text-right">Kredit</th>
                        <th class="text-right">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ledger['rows'] as $row)
                        <tr>
                            <td>{{ $row->date->format('d/m/Y') }}</td>
                            <td class="font-mono text-xs">{{ $row->number }}</td>
                            <td>{{ $row->description }}</td>
                            <td class="text-right">{{ $row->debit > 0 ? Money::rupiah($row->debit) : '-' }}</td>
                            <td class="text-right">{{ $row->credit > 0 ? Money::rupiah($row->credit) : '-' }}</td>
                            <td class="text-right">{{ Money::rupiah($row->balance) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted py-6 text-center">Tidak ada mutasi di rentang ini.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="font-medium">
                        <td colspan="5">Saldo akhir</td>
                        <td class="text-right">{{ Money::rupiah($ledger['closing']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</x-layouts.app>
