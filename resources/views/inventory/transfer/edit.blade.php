<x-layouts.app :title="'Ubah '.$transfer->number">
    <x-page-header :title="'Ubah '.$transfer->number" subtitle="Draft masih bisa diubah sebelum barang dikirim."
                   :back="route('inventory.transfers.detail', $transfer)" />

    @livewire('inventory.transfer-form', ['transfer' => $transfer])
</x-layouts.app>
