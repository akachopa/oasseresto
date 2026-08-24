<x-layouts.app title="Penerimaan Barang">
    <x-page-header title="Penerimaan Barang"
                   subtitle="Satu-satunya jalan stok pembelian masuk. Posting penerimaan membentuk batch dan menulis kartu stok.">
        <x-slot:actions>
            <a href="{{ route('purchase.receipts.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" /> Terima Barang
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-goods-receipts"
        :url="route('purchase.receipts.data')"
        :order="[[2, 'desc']]"
        :columns="[
            ['data' => 'number', 'title' => 'Nomor'],
            ['data' => 'date', 'title' => 'Tanggal'],
            ['data' => 'supplier', 'title' => 'Supplier'],
            ['data' => 'warehouse', 'title' => 'Gudang'],
            ['data' => 'order', 'title' => 'PO'],
            ['data' => 'value', 'title' => 'Nilai', 'className' => 'text-right'],
            ['data' => 'status', 'title' => 'Status'],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-gr-supplier">Supplier</label>
                <select id="filter-gr-supplier" name="supplier" data-table-filter="tbl-goods-receipts" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($suppliers as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-gr-warehouse">Gudang</label>
                <select id="filter-gr-warehouse" name="warehouse" data-table-filter="tbl-goods-receipts" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($warehouses as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-gr-status">Status</label>
                <select id="filter-gr-status" name="status" data-table-filter="tbl-goods-receipts" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
