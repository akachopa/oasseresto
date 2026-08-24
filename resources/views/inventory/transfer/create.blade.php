<x-layouts.app title="Buat Transfer Stok">
    <x-page-header title="Buat Transfer Stok" subtitle="Kuantitas dicatat dalam satuan yang dipilih, lalu dikonversi ke satuan dasar."
                   :back="route('inventory.transfers.index')" />

    @livewire('inventory.transfer-form')
</x-layouts.app>
