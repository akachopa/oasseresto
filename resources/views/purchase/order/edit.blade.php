<x-layouts.app :title="'Ubah '.$order->number">
    <x-page-header :title="'Ubah '.$order->number"
                   subtitle="Purchase order hanya bisa diubah selama belum diajukan."
                   :back="route('purchase.orders.detail', $order)" />

    @livewire('purchase.order-form', ['order' => $order])
</x-layouts.app>
