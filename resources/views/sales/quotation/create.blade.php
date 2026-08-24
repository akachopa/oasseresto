<x-layouts.app title="Buat Penawaran">
    <x-page-header title="Buat Penawaran"
                   subtitle="Harga otomatis mengikuti level harga customer dan aturan promo yang berlaku."
                   :back="route('sales.quotations.index')" />

    @livewire('sales.quotation-form', ['customerId' => $customerId])
</x-layouts.app>
