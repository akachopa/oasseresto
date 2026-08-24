<x-layouts.app title="Tambah Supplier">
    <x-page-header title="Tambah Supplier" subtitle="Daftarkan barang yang dijual supplier agar purchasing bisa membandingkan harga."
                   :back="route('suppliers.index')" />

    @livewire('supplier.supplier-form')
</x-layouts.app>
