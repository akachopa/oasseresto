<x-layouts.app title="Order Penjualan">
    <x-page-header title="Order Penjualan"
                   subtitle="Order yang disetujui menahan stok gudang sehingga barang tidak dijanjikan dua kali.">
        <x-slot:actions>
            @can('sales.create')
                <a href="{{ route('sales.orders.create') }}" wire:navigate class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" /> Buat Order
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-sales-orders"
        :url="route('sales.orders.data')"
        :order="[[2, 'desc']]"
        :columns="[
            ['data' => 'number', 'title' => 'Nomor'],
            ['data' => 'date', 'title' => 'Tanggal'],
            ['data' => 'customer', 'title' => 'Customer'],
            ['data' => 'warehouse', 'title' => 'Gudang'],
            ['data' => 'total', 'title' => 'Total', 'className' => 'text-right'],
            ['data' => 'credit', 'title' => 'Kredit'],
            ['data' => 'status', 'title' => 'Status'],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-so-customer">Customer</label>
                <select id="filter-so-customer" name="customer" data-table-filter="tbl-sales-orders" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($customers as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-so-warehouse">Gudang</label>
                <select id="filter-so-warehouse" name="warehouse" data-table-filter="tbl-sales-orders" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($warehouses as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-so-status">Status</label>
                <select id="filter-so-status" name="status" data-table-filter="tbl-sales-orders" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-so-outstanding">Belum Tuntas</label>
                <select id="filter-so-outstanding" name="outstanding" data-table-filter="tbl-sales-orders" class="input w-auto">
                    <option value="">Semua</option>
                    <option value="1">Hanya yang masih berjalan</option>
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
