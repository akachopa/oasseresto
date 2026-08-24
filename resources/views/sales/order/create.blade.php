<x-layouts.app title="Buat Order Penjualan">
    <x-page-header title="Buat Order Penjualan"
                   subtitle="Sisa stok yang bisa dijanjikan sudah dikurangi reservasi order lain."
                   :back="route('sales.orders.index')" />

    @livewire('sales.order-form', ['customerId' => $customerId])
</x-layouts.app>
