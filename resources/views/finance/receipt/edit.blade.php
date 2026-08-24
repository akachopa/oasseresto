<x-layouts.app :title="'Ubah '.$receipt->number">
    <x-page-header :title="'Ubah '.$receipt->number"
                   subtitle="Penerimaan hanya bisa diubah selama belum diposting."
                   :back="route('finance.receipts.detail', $receipt)" />

    @livewire('finance.receipt-form', ['receipt' => $receipt])
</x-layouts.app>
