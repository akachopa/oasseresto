<x-layouts.app :title="'Ubah '.$order->number">
    <x-page-header :title="'Ubah '.$order->number"
                   subtitle="Order hanya bisa diubah selama belum diajukan."
                   :back="route('sales.orders.detail', $order)" />

    @livewire('sales.order-form', ['order' => $order])
</x-layouts.app>
