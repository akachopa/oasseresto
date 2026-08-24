<x-layouts.app title="Antrian Picking">
    <x-page-header title="Antrian Picking"
                   subtitle="Daftar surat jalan yang masih perlu disiapkan gudang. Kuantitas boleh kurang bila stok fisik tidak cukup.">
        <x-slot:actions>
            <a href="{{ route('delivery.orders.index') }}" wire:navigate class="btn-secondary">
                <x-icon name="truck" class="h-4 w-4" /> Semua Surat Jalan
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-picking"
        :url="route('delivery.picking.data')"
        :order="[[2, 'asc']]"
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
                <label class="label" for="filter-pick-warehouse">Gudang</label>
                <select id="filter-pick-warehouse" name="warehouse" data-table-filter="tbl-picking" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($warehouses as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
