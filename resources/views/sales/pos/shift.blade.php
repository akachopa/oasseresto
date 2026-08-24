@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Shift Kasir">
    <x-page-header title="Shift Kasir"
                   subtitle="Kas fisik dihitung terhadap perkiraan kas shift; selisihnya tercatat sebagai tanggung jawab kasir.">
        <x-slot:actions>
            @if ($shift)
                <a href="{{ route('pos.index') }}" wire:navigate class="btn-primary">
                    <x-icon name="cash-register" class="h-4 w-4" /> Buka Kasir
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($shift)
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <x-kpi label="Nomor Shift" :value="$shift->number"
                   :hint="'dibuka '.$shift->opened_at->format('d/m/Y H:i')" />
            <x-kpi label="Transaksi" :value="(string) $summary['transaction_count']" />
            <x-kpi label="Penjualan" :value="Money::compact($summary['gross_sales'])"
                   :hint="'tunai '.Money::compact($summary['cash_sales'])" />
            <x-kpi label="Perkiraan Kas" :value="Money::compact($summary['expected_cash'])" />
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            @can('pos.shift.close')
                <form method="POST" action="{{ route('pos.shift.close', $shift) }}" class="card card-pad space-y-3">
                    @csrf
                    <p class="section-title">Tutup Shift</p>

                    <div>
                        <label class="label" for="counted_cash">Kas Fisik Dihitung</label>
                        <input id="counted_cash" name="counted_cash" type="number" step="0.01" min="0" required
                               class="input text-right">
                    </div>

                    <div>
                        <label class="label" for="note">Catatan</label>
                        <input id="note" name="note" class="input" placeholder="Penjelasan selisih bila ada">
                    </div>

                    <button type="submit" class="btn-primary">
                        <x-icon name="check" class="h-4 w-4" /> Tutup Shift
                    </button>
                </form>
            @endcan

            @can('pos.sell')
                <form method="POST" action="{{ route('pos.shift.cash', $shift) }}" class="card card-pad space-y-3">
                    @csrf
                    <p class="section-title">Mutasi Kas</p>
                    <p class="text-muted text-sm">
                        Catat setoran ke kasir atau pengambilan uang dari drawer selama shift berjalan.
                    </p>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label" for="amount">Nilai</label>
                            <input id="amount" name="amount" type="number" step="0.01" min="0" required
                                   class="input text-right">
                        </div>

                        <div>
                            <label class="label" for="direction">Arah</label>
                            <select id="direction" name="direction" class="input">
                                <option value="in">Kas masuk</option>
                                <option value="out">Kas keluar</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="label" for="cash-note">Keterangan</label>
                        <input id="cash-note" name="note" class="input">
                    </div>

                    <button type="submit" class="btn-secondary">
                        <x-icon name="wallet" class="h-4 w-4" /> Catat Mutasi
                    </button>
                </form>
            @endcan
        </div>
    @else
        <div class="card">
            <x-empty-state icon="clock" title="Tidak ada shift terbuka"
                           message="Buka shift dari halaman kasir untuk mulai transaksi.">
                <x-slot:actions>
                    <a href="{{ route('pos.index') }}" wire:navigate class="btn-primary">
                        <x-icon name="cash-register" class="h-4 w-4" /> Ke Halaman Kasir
                    </a>
                </x-slot:actions>
            </x-empty-state>
        </div>
    @endif

    <div class="card card-pad mt-4 space-y-3">
        <p class="section-title">Riwayat Shift</p>

        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Dibuka</th>
                        <th>Ditutup</th>
                        <th class="text-right">Penjualan</th>
                        <th class="text-right">Selisih Kas</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($history as $row)
                        <tr>
                            <td class="font-mono text-xs">{{ $row->number }}</td>
                            <td>{{ $row->opened_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $row->closed_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            <td class="text-right">{{ Money::rupiah($row->totalSales()) }}</td>
                            <td class="text-right {{ $row->difference < 0 ? 'text-negative' : '' }}">
                                {{ Money::rupiah($row->difference) }}
                            </td>
                            <td>
                                <span class="badge-{{ $row->isOpen() ? 'info' : 'success' }}">
                                    {{ $row->isOpen() ? 'Terbuka' : 'Ditutup' }}
                                </span>
                            </td>
                            <td class="text-right">
                                <a href="{{ route('pos.shift.detail', $row) }}" wire:navigate
                                   class="btn-icon text-muted hover:bg-panel-soft hover:text-brand-600">
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-muted py-6 text-center">Belum ada shift.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
