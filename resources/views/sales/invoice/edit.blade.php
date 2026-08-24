<x-layouts.app :title="'Ubah '.$invoice->number">
    <x-page-header :title="'Ubah '.$invoice->number"
                   subtitle="Invoice hanya bisa diubah selama belum diposting."
                   :back="route('sales.invoices.detail', $invoice)" />

    @livewire('sales.invoice-form', ['invoice' => $invoice])
</x-layouts.app>
