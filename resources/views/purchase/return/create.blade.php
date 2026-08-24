<x-layouts.app title="Buat Retur Pembelian">
    <x-page-header title="Buat Retur Pembelian"
                   subtitle="Pilih invoice supaya barang yang dikembalikan memang pernah ditagih supplier tersebut."
                   :back="route('purchase.returns.index')" />

    @livewire('purchase.return-form', ['invoiceId' => $invoiceId])
</x-layouts.app>
