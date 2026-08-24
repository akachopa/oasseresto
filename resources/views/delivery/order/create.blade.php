<x-layouts.app title="Buat Surat Jalan">
    <x-page-header title="Buat Surat Jalan"
                   subtitle="Pilih sales order untuk menarik sisa barang yang belum dikirim."
                   :back="route('delivery.orders.index')" />

    @livewire('delivery.delivery-form', ['orderId' => $orderId])
</x-layouts.app>
