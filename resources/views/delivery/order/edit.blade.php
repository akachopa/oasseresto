<x-layouts.app :title="'Ubah '.$delivery->number">
    <x-page-header :title="'Ubah '.$delivery->number"
                   subtitle="Surat jalan hanya bisa diubah sebelum barang dikirim."
                   :back="route('delivery.orders.detail', $delivery)" />

    @livewire('delivery.delivery-form', ['delivery' => $delivery])
</x-layouts.app>
