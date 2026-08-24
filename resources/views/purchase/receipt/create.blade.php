<x-layouts.app title="Terima Barang">
    <x-page-header title="Terima Barang"
                   subtitle="Pilih PO untuk menarik sisa yang belum dikirim; koreksi hanya bila ada kekurangan atau barang rusak."
                   :back="route('purchase.receipts.index')" />

    @livewire('purchase.receipt-form', ['orderId' => $orderId])
</x-layouts.app>
