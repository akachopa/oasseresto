<x-layouts.app title="Stok">
    <x-page-header title="Stok" subtitle="Saldo per produk per gudang, sudah memperhitungkan reservasi order.">
        <x-slot:actions>
            <a href="{{ route('inventory.ledger.index') }}" wire:navigate class="btn-secondary">
                <x-icon name="list" class="h-4 w-4" /> Kartu Stok
            </a>
            @can('inventory.adjust')
                <a href="{{ route('inventory.adjustments.create') }}" wire:navigate class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" /> Penyesuaian
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-stock"
        :url="route('inventory.stock.data')"
        :order="[[2, 'asc']]"
        :columns="[
            ['data' => 'sku', 'title' => 'SKU'],
            ['data' => 'product', 'title' => 'Produk'],
            ['data' => 'warehouse', 'title' => 'Gudang'],
            ['data' => 'quantity', 'title' => 'Saldo', 'className' => 'text-right'],
            ['data' => 'reserved', 'title' => 'Dialokasikan', 'className' => 'text-right'],
            ['data' => 'reorder_point', 'title' => 'Titik Reorder', 'className' => 'text-right'],
            ['data' => 'average_cost', 'title' => 'HPP', 'className' => 'text-right', 'orderable' => false],
            ['data' => 'value', 'title' => 'Nilai', 'className' => 'text-right'],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-stock-warehouse">Gudang</label>
                <select id="filter-stock-warehouse" name="warehouse" data-table-filter="tbl-stock" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($warehouses as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-stock-category">Kategori</label>
                <select id="filter-stock-category" name="category" data-table-filter="tbl-stock" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($categories as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-stock-condition">Kondisi</label>
                <select id="filter-stock-condition" name="condition" data-table-filter="tbl-stock" class="input w-auto">
                    <option value="">Semua</option>
                    <option value="available">Ada stok</option>
                    <option value="below_reorder">Di bawah titik reorder</option>
                    <option value="empty">Kosong</option>
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
