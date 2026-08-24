@php
    use App\Modules\Core\Support\Money;
    use App\Modules\Finance\Models\CashTransaction;
@endphp

<x-layouts.app title="Kas & Bank">
    <x-page-header title="Kas & Bank"
                   subtitle="Saldo setiap akun terbentuk dari mutasi buku kas, bukan diisi manual.">
        <x-slot:actions>
            @can('finance.cash.manage')
                <a href="{{ route('finance.cash.create') }}" wire:navigate class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" /> Tambah Akun
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Total Saldo" :value="Money::compact($total)"
               :hint="$accounts->count().' akun'" icon="wallet" />

        @foreach ($accounts->where('is_active', true)->take(3) as $account)
            <x-kpi :label="$account->name" :value="Money::compact($account->balance)"
                   :hint="$account->typeLabel()"
                   :href="route('finance.cash.detail', $account)" />
        @endforeach
    </div>

    <div class="card card-pad mt-4 space-y-3">
        <p class="section-title">Daftar Akun</p>

        <div class="oasse-table-wrap">
            <table class="table-oasse">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Jenis</th>
                        <th>Cabang</th>
                        <th class="text-right">Saldo</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $account)
                        <tr>
                            <td class="font-mono text-xs">{{ $account->code }}</td>
                            <td class="font-medium">
                                {{ $account->name }}
                                @if ($account->account_number)
                                    <span class="text-muted block text-xs">
                                        {{ $account->bank_name }} · {{ $account->account_number }}
                                    </span>
                                @endif
                            </td>
                            <td>{{ $account->typeLabel() }}</td>
                            <td>{{ $account->branch?->name ?? 'Semua cabang' }}</td>
                            <td class="text-right font-medium">{{ Money::rupiah($account->balance) }}</td>
                            <td>
                                <span class="badge-{{ $account->is_active ? 'success' : 'muted' }}">
                                    {{ $account->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td>
                                <x-row-actions :detail="route('finance.cash.detail', $account)"
                                               :edit="auth()->user()->can('finance.cash.manage') ? route('finance.cash.edit', $account) : null"
                                               :delete="auth()->user()->can('finance.cash.manage') ? route('finance.cash.hapus', $account) : null" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-muted py-6 text-center">Belum ada akun kas/bank.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        <p class="section-title mb-2">Buku Kas</p>

        <x-data-table
            id="tbl-cash-book"
            :url="route('finance.cash.data')"
            :order="[[1, 'desc']]"
            :columns="[
                ['data' => 'date', 'title' => 'Tanggal'],
                ['data' => 'account', 'title' => 'Akun'],
                ['data' => 'category', 'title' => 'Kategori'],
                ['data' => 'document', 'title' => 'Dokumen'],
                ['data' => 'amount', 'title' => 'Nilai', 'className' => 'text-right'],
                ['data' => 'balance', 'title' => 'Saldo', 'className' => 'text-right'],
            ]">
            <x-slot:filters>
                <div>
                    <label class="label" for="filter-cash-account">Akun</label>
                    <select id="filter-cash-account" name="account" data-table-filter="tbl-cash-book" class="input w-auto">
                        <option value="">Semua</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label" for="filter-cash-category">Kategori</label>
                    <select id="filter-cash-category" name="category" data-table-filter="tbl-cash-book" class="input w-auto">
                        <option value="">Semua</option>
                        @foreach (CashTransaction::categories() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label" for="filter-cash-direction">Arah</label>
                    <select id="filter-cash-direction" name="direction" data-table-filter="tbl-cash-book" class="input w-auto">
                        <option value="">Semua</option>
                        <option value="in">Kas masuk</option>
                        <option value="out">Kas keluar</option>
                    </select>
                </div>
            </x-slot:filters>
        </x-data-table>
    </div>
</x-layouts.app>
