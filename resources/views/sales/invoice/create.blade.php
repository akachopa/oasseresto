<x-layouts.app title="Buat Invoice Penjualan">
    <x-page-header title="Buat Invoice Penjualan"
                   subtitle="Pilih surat jalan agar tagihan hanya mencakup barang yang sudah keluar dan belum ditagih."
                   :back="route('sales.invoices.index')" />

    @livewire('sales.invoice-form', ['deliveryId' => $deliveryId])
</x-layouts.app>
