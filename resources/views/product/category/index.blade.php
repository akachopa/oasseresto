<x-layouts.app title="Kategori Produk">
    <x-page-header title="Kategori Produk" subtitle="Kelompokkan produk untuk laporan dan aturan harga.">
        <x-slot:actions>
            <a href="{{ route('product-categories.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" /> Tambah Kategori
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-categories"
        :url="route('product-categories.data')"
        :order="[[1, 'asc']]"
        :columns="[
            ['data' => 'code', 'title' => 'Kode'],
            ['data' => 'name', 'title' => 'Nama Kategori'],
            ['data' => 'parent', 'title' => 'Induk', 'orderable' => false],
            ['data' => 'products', 'title' => 'Produk', 'orderable' => false],
            ['data' => 'status', 'title' => 'Status', 'orderable' => false],
        ]" />
</x-layouts.app>
