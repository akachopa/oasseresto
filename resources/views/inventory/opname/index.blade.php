@php
    $statuses = \App\Modules\Inventory\Controllers\StockOpnameController::statusOptions();
@endphp

<x-layouts.app title="Stock Opname">
    <x-page-header title="Stock Opname" subtitle="Saldo sistem dibaca ulang saat posting agar transaksi selama penghitungan tetap terhitung.">
        <x-slot:actions>
            <a href="{{ route('inventory.opnames.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" /> Buat Opname
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-opnames"
        :url="route('inventory.opnames.data')"
        :order="[[2, 'desc']]"
        :columns="[
            ['data' => 'number', 'title' => 'Nomor'],
            ['data' => 'date', 'title' => 'Tanggal'],
            ['data' => 'warehouse', 'title' => 'Gudang'],
            ['data' => 'difference_count', 'title' => 'Baris Selisih', 'className' => 'text-right'],
            ['data' => 'difference_value', 'title' => 'Nilai Selisih', 'className' => 'text-right'],
            ['data' => 'status', 'title' => 'Status'],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-opname-warehouse">Gudang</label>
                <select id="filter-opname-warehouse" name="warehouse" data-table-filter="tbl-opnames" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($warehouses as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-opname-status">Status</label>
                <select id="filter-opname-status" name="status" data-table-filter="tbl-opnames" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
