<x-layouts.app :title="'Ubah '.$invoice->number">
    <x-page-header :title="'Ubah '.$invoice->number"
                   subtitle="Invoice hanya bisa diubah selama belum diposting."
                   :back="route('purchase.invoices.detail', $invoice)" />

    @livewire('purchase.invoice-form', ['invoice' => $invoice])
</x-layouts.app>
