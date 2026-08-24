<x-layouts.app title="Buat Retur Penjualan">
    <x-page-header title="Buat Retur Penjualan"
                   subtitle="Pilih invoice supaya jumlah retur tidak melebihi yang pernah ditagih."
                   :back="route('sales.returns.index')" />

    @livewire('sales.return-form', ['invoiceId' => $invoiceId])
</x-layouts.app>
