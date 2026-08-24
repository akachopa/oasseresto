<x-layouts.app title="Purchase Order">
    <x-page-header title="Purchase Order"
                   subtitle="Komitmen resmi ke supplier. Penerimaan barang hanya boleh atas PO yang sudah disetujui.">
        <x-slot:actions>
            @can('purchase.create')
                <a href="{{ route('purchase.orders.create') }}" wire:navigate class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" /> Buat Purchase Order
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-purchase-orders"
        :url="route('purchase.orders.data')"
        :order="[[2, 'desc']]"
        :columns="[
            ['data' => 'number', 'title' => 'Nomor'],
            ['data' => 'date', 'title' => 'Tanggal'],
            ['data' => 'supplier', 'title' => 'Supplier'],
            ['data' => 'warehouse', 'title' => 'Gudang'],
            ['data' => 'expected', 'title' => 'Perkiraan Datang'],
            ['data' => 'total', 'title' => 'Total', 'className' => 'text-right'],
            ['data' => 'status', 'title' => 'Status'],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-po-supplier">Supplier</label>
                <select id="filter-po-supplier" name="supplier" data-table-filter="tbl-purchase-orders" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($suppliers as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-po-warehouse">Gudang</label>
                <select id="filter-po-warehouse" name="warehouse" data-table-filter="tbl-purchase-orders" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($warehouses as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-po-status">Status</label>
                <select id="filter-po-status" name="status" data-table-filter="tbl-purchase-orders" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-po-outstanding">Belum Tuntas</label>
                <select id="filter-po-outstanding" name="outstanding" data-table-filter="tbl-purchase-orders" class="input w-auto">
                    <option value="">Semua</option>
                    <option value="1">Hanya yang masih ditunggu</option>
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
