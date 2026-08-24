<x-layouts.app title="Brand">
    <x-page-header title="Brand" subtitle="Merek produk untuk analisis penjualan dan pembelian.">
        <x-slot:actions>
            <a href="{{ route('brands.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" /> Tambah Brand
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-brands"
        :url="route('brands.data')"
        :order="[[1, 'asc']]"
        :columns="[
            ['data' => 'code', 'title' => 'Kode'],
            ['data' => 'name', 'title' => 'Nama Brand'],
            ['data' => 'products', 'title' => 'Produk', 'orderable' => false],
            ['data' => 'status', 'title' => 'Status', 'orderable' => false],
        ]" />
</x-layouts.app>
