<x-layouts.app :title="'Ubah '.$quotation->number">
    <x-page-header :title="'Ubah '.$quotation->number"
                   subtitle="Penawaran hanya bisa diubah selama belum dikirim."
                   :back="route('sales.quotations.detail', $quotation)" />

    @livewire('sales.quotation-form', ['quotation' => $quotation])
</x-layouts.app>
