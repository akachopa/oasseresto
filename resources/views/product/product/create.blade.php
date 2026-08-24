<x-layouts.app title="Tambah Produk">
    <x-page-header title="Tambah Produk" subtitle="Satuan dasar menentukan semua kalkulasi stok dan HPP."
                   :back="route('products.index')" />

    @livewire('product.product-form')
</x-layouts.app>
