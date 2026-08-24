<x-layouts.app title="Retur Penjualan">
    <x-page-header title="Retur Penjualan"
                   subtitle="Barang layak jual masuk kembali ke stok; barang rusak dicatat tanpa menambah stok.">
        <x-slot:actions>
            <a href="{{ route('sales.returns.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" /> Buat Retur
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-sales-returns"
        :url="route('sales.returns.data')"
        :order="[[2, 'desc']]"
        :columns="[
            ['data' => 'number', 'title' => 'Nomor'],
            ['data' => 'date', 'title' => 'Tanggal'],
            ['data' => 'customer', 'title' => 'Customer'],
            ['data' => 'warehouse', 'title' => 'Gudang'],
            ['data' => 'reason', 'title' => 'Alasan'],
            ['data' => 'total', 'title' => 'Nilai', 'className' => 'text-right'],
            ['data' => 'status', 'title' => 'Status'],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-sr-customer">Customer</label>
                <select id="filter-sr-customer" name="customer" data-table-filter="tbl-sales-returns" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($customers as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-sr-warehouse">Gudang</label>
                <select id="filter-sr-warehouse" name="warehouse" data-table-filter="tbl-sales-returns" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($warehouses as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-sr-reason">Alasan</label>
                <select id="filter-sr-reason" name="reason" data-table-filter="tbl-sales-returns" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($reasons as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-sr-status">Status</label>
                <select id="filter-sr-status" name="status" data-table-filter="tbl-sales-returns" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
