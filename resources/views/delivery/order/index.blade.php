<x-layouts.app title="Pengiriman">
    <x-page-header title="Surat Jalan"
                   subtitle="Stok penjualan hanya keluar lewat surat jalan yang sudah dipicking dan dikirim.">
        <x-slot:actions>
            <a href="{{ route('delivery.picking.index') }}" wire:navigate class="btn-secondary">
                <x-icon name="clipboard" class="h-4 w-4" /> Antrian Picking
            </a>

            @can('delivery.create')
                <a href="{{ route('delivery.orders.create') }}" wire:navigate class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" /> Buat Surat Jalan
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-deliveries"
        :url="route('delivery.orders.data')"
        :order="[[2, 'desc']]"
        :columns="[
            ['data' => 'number', 'title' => 'Nomor'],
            ['data' => 'date', 'title' => 'Tanggal'],
            ['data' => 'customer', 'title' => 'Customer'],
            ['data' => 'warehouse', 'title' => 'Gudang'],
            ['data' => 'order', 'title' => 'Sales Order'],
            ['data' => 'status', 'title' => 'Status'],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-do-warehouse">Gudang</label>
                <select id="filter-do-warehouse" name="warehouse" data-table-filter="tbl-deliveries" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($warehouses as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-do-status">Status</label>
                <select id="filter-do-status" name="status" data-table-filter="tbl-deliveries" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
