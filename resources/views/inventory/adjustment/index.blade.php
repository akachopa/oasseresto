@php
    $statuses = \App\Modules\Inventory\Controllers\StockAdjustmentController::statusOptions();
@endphp

<x-layouts.app title="Penyesuaian Stok">
    <x-page-header title="Penyesuaian Stok" subtitle="Setiap penyesuaian wajib memakai alasan agar selisih stok bisa dipertanggungjawabkan.">
        <x-slot:actions>
            <a href="{{ route('inventory.adjustments.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" /> Buat Penyesuaian
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-adjustments"
        :url="route('inventory.adjustments.data')"
        :order="[[2, 'desc']]"
        :columns="[
            ['data' => 'number', 'title' => 'Nomor'],
            ['data' => 'date', 'title' => 'Tanggal'],
            ['data' => 'warehouse', 'title' => 'Gudang'],
            ['data' => 'reason', 'title' => 'Alasan'],
            ['data' => 'value', 'title' => 'Nilai', 'className' => 'text-right'],
            ['data' => 'status', 'title' => 'Status'],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-adjustment-warehouse">Gudang</label>
                <select id="filter-adjustment-warehouse" name="warehouse" data-table-filter="tbl-adjustments" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($warehouses as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-adjustment-reason">Alasan</label>
                <select id="filter-adjustment-reason" name="reason" data-table-filter="tbl-adjustments" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($reasons as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-adjustment-status">Status</label>
                <select id="filter-adjustment-status" name="status" data-table-filter="tbl-adjustments" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
