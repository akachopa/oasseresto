<x-layouts.app title="Produk">
    <x-page-header title="Produk" subtitle="Katalog barang, satuan, dan harga jual.">
        <x-slot:actions>
            <x-export-buttons type="products" />
            @can('product.create')
                <a href="{{ route('products.create') }}" wire:navigate class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" /> Tambah Produk
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-products"
        :url="route('products.data')"
        :order="[[2, 'asc']]"
        :columns="[
            ['data' => 'sku', 'title' => 'SKU'],
            ['data' => 'name', 'title' => 'Nama Produk'],
            ['data' => 'category', 'title' => 'Kategori'],
            ['data' => 'brand', 'title' => 'Brand'],
            ['data' => 'unit', 'title' => 'Satuan'],
            ['data' => 'price', 'title' => 'Harga Dasar', 'className' => 'text-right'],
            ['data' => 'cost', 'title' => 'HPP Rata-rata', 'className' => 'text-right'],
            ['data' => 'status', 'title' => 'Status', 'orderable' => false],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-product-category">Kategori</label>
                <select id="filter-product-category" name="category" data-table-filter="tbl-products" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($categories as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-product-brand">Brand</label>
                <select id="filter-product-brand" name="brand" data-table-filter="tbl-products" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($brands as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-product-active">Status</label>
                <select id="filter-product-active" name="is_active" data-table-filter="tbl-products" class="input w-auto">
                    <option value="">Semua</option>
                    <option value="1">Aktif</option>
                    <option value="0">Nonaktif</option>
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
