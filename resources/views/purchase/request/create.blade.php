<x-layouts.app title="Buat Purchase Request">
    <x-page-header title="Buat Purchase Request"
                   subtitle="Isi kebutuhan barang; estimasi harga memakai harga beli terakhir."
                   :back="route('purchase.requests.index')" />

    @livewire('purchase.request-form')
</x-layouts.app>
