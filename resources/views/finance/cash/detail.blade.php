@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app :title="$account->name">
    <x-page-header :title="$account->name"
                   :subtitle="$account->typeLabel().($account->account_number ? ' · '.$account->account_number : '')"
                   :back="route('finance.cash.index')">
        <x-slot:actions>
            @can('finance.cash.manage')
                <a href="{{ route('finance.cash.edit', $account) }}" wire:navigate class="btn-secondary">
                    <x-icon name="pencil" class="h-4 w-4" /> Ubah
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Saldo Sekarang" :value="Money::compact($account->balance)" icon="wallet" />
        <x-kpi label="Saldo Awal" :value="Money::compact($account->opening_balance)" />
        <x-kpi label="Jumlah Mutasi" :value="(string) $account->transactions()->count()" />
        <x-kpi label="Cabang" :value="$account->branch?->name ?? 'Semua cabang'" />
    </div>

    @can('finance.cash.manage')
        <form method="POST" action="{{ route('finance.cash.transfer', $account) }}"
              class="card card-pad mt-4 space-y-3">
            @csrf
            <p class="section-title">Transfer ke Akun Lain</p>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <div>
                    <label class="label" for="to_cash_account_id">Akun Tujuan</label>
                    <select id="to_cash_account_id" name="to_cash_account_id" class="input" required>
                        <option value="">Pilih akun</option>
                        @foreach ($accounts as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label" for="amount">Nilai</label>
                    <input id="amount" name="amount" type="number" step="0.01" min="0" required class="input text-right">
                </div>

                <div class="sm:col-span-2">
                    <label class="label" for="note">Keterangan</label>
                    <input id="note" name="note" class="input" placeholder="Contoh: setor tunai ke bank">
                </div>
            </div>

            <button type="submit" class="btn-secondary">
                <x-icon name="arrow-up" class="h-4 w-4" /> Catat Transfer
            </button>
        </form>
    @endcan

    <div class="card card-pad mt-4 space-y-3">
        <p class="section-title">Mutasi Terakhir</p>

        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Kategori</th>
                        <th>Keterangan</th>
                        <th class="text-right">Masuk</th>
                        <th class="text-right">Keluar</th>
                        <th class="text-right">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $row)
                        <tr>
                            <td>{{ $row->transaction_date->format('d/m/Y') }}</td>
                            <td>{{ $row->categoryLabel() }}</td>
                            <td>
                                {{ $row->description }}
                                @if ($row->document_number)
                                    <span class="text-muted block font-mono text-xs">{{ $row->document_number }}</span>
                                @endif
                            </td>
                            <td class="text-right text-positive">
                                {{ $row->isInbound() ? Money::rupiah($row->amount) : '-' }}
                            </td>
                            <td class="text-right text-negative">
                                {{ $row->isInbound() ? '-' : Money::rupiah($row->amount) }}
                            </td>
                            <td class="text-right font-medium">{{ Money::rupiah($row->balance_after) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted py-6 text-center">Belum ada mutasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
