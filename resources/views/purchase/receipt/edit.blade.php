<x-layouts.app :title="'Ubah '.$receipt->number">
    <x-page-header :title="'Ubah '.$receipt->number"
                   subtitle="Penerimaan hanya bisa diubah selama belum diposting."
                   :back="route('purchase.receipts.detail', $receipt)" />

    @livewire('purchase.receipt-form', ['receipt' => $receipt])
</x-layouts.app>
