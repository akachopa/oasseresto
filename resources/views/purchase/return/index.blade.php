<x-layouts.app title="Retur Pembelian">
    <x-page-header title="Retur Pembelian"
                   subtitle="Mengeluarkan barang dari stok dan, bila diselesaikan sebagai credit note, mengurangi hutang supplier.">
        <x-slot:actions>
            <a href="{{ route('purchase.returns.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" /> Buat Retur
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-purchase-returns"
        :url="route('purchase.returns.data')"
        :order="[[2, 'desc']]"
        :columns="[
            ['data' => 'number', 'title' => 'Nomor'],
            ['data' => 'date', 'title' => 'Tanggal'],
            ['data' => 'supplier', 'title' => 'Supplier'],
            ['data' => 'warehouse', 'title' => 'Gudang'],
            ['data' => 'reason', 'title' => 'Alasan'],
            ['data' => 'total', 'title' => 'Nilai', 'className' => 'text-right'],
            ['data' => 'status', 'title' => 'Status'],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-prt-supplier">Supplier</label>
                <select id="filter-prt-supplier" name="supplier" data-table-filter="tbl-purchase-returns" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($suppliers as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-prt-warehouse">Gudang</label>
                <select id="filter-prt-warehouse" name="warehouse" data-table-filter="tbl-purchase-returns" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($warehouses as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-prt-reason">Alasan</label>
                <select id="filter-prt-reason" name="reason" data-table-filter="tbl-purchase-returns" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($reasons as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-prt-status">Status</label>
                <select id="filter-prt-status" name="status" data-table-filter="tbl-purchase-returns" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
