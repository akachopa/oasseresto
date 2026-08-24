<x-layouts.app title="Buat Invoice Pembelian">
    <x-page-header title="Buat Invoice Pembelian"
                   subtitle="Pilih penerimaan agar tagihan hanya mencakup barang yang sudah masuk dan belum ditagih."
                   :back="route('purchase.invoices.index')" />

    @livewire('purchase.invoice-form', ['receiptId' => $receiptId])
</x-layouts.app>
