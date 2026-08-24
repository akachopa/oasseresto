<x-layouts.app title="Buat Purchase Order">
    <x-page-header title="Buat Purchase Order"
                   subtitle="Harga otomatis mengikuti harga terakhir supplier untuk produk tersebut."
                   :back="route('purchase.orders.index')" />

    @livewire('purchase.order-form', ['supplierId' => $supplierId])
</x-layouts.app>
