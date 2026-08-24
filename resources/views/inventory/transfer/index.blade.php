@php
    $statuses = \App\Modules\Inventory\Controllers\StockTransferController::statusOptions();
@endphp

<x-layouts.app title="Transfer Stok">
    <x-page-header title="Transfer Stok" subtitle="Barang yang sudah dikirim tapi belum diterima berstatus dalam perjalanan.">
        <x-slot:actions>
            <a href="{{ route('inventory.transfers.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" /> Buat Transfer
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-transfers"
        :url="route('inventory.transfers.data')"
        :order="[[2, 'desc']]"
        :columns="[
            ['data' => 'number', 'title' => 'Nomor'],
            ['data' => 'date', 'title' => 'Tanggal'],
            ['data' => 'from', 'title' => 'Dari Gudang'],
            ['data' => 'to', 'title' => 'Ke Gudang'],
            ['data' => 'value', 'title' => 'Nilai', 'className' => 'text-right'],
            ['data' => 'status', 'title' => 'Status'],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-transfer-warehouse">Gudang</label>
                <select id="filter-transfer-warehouse" name="warehouse" data-table-filter="tbl-transfers" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($warehouses as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-transfer-status">Status</label>
                <select id="filter-transfer-status" name="status" data-table-filter="tbl-transfers" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
