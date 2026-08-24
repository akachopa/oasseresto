@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Biaya">
    <x-page-header title="Biaya Operasional"
                   subtitle="Biaya di atas ambang kategori wajib disetujui sebelum kas keluar.">
        <x-slot:actions>
            @can('finance.expense.create')
                <a href="{{ route('finance.expenses.create') }}" wire:navigate class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" /> Input Biaya
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Biaya Bulan Ini" :value="Money::compact($monthTotal)" icon="wallet" />
        <x-kpi label="Menunggu Approval" :value="Money::compact($pendingTotal)"
               :tone="$pendingTotal > 0 ? 'caution' : 'positive'" />
    </div>

    <div class="mt-4">
        <x-data-table
            id="tbl-expenses"
            :url="route('finance.expenses.data')"
            :order="[[2, 'desc']]"
            :columns="[
                ['data' => 'number', 'title' => 'Nomor'],
                ['data' => 'date', 'title' => 'Tanggal'],
                ['data' => 'category', 'title' => 'Kategori'],
                ['data' => 'payee', 'title' => 'Penerima'],
                ['data' => 'total', 'title' => 'Nilai', 'className' => 'text-right'],
                ['data' => 'status', 'title' => 'Status'],
            ]">
            <x-slot:filters>
                <div>
                    <label class="label" for="filter-exp-category">Kategori</label>
                    <select id="filter-exp-category" name="category" data-table-filter="tbl-expenses" class="input w-auto">
                        <option value="">Semua</option>
                        @foreach ($categories as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label" for="filter-exp-status">Status</label>
                    <select id="filter-exp-status" name="status" data-table-filter="tbl-expenses" class="input w-auto">
                        <option value="">Semua</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </x-slot:filters>
        </x-data-table>
    </div>
</x-layouts.app>
