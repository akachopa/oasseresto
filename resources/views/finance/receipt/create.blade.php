<x-layouts.app title="Terima Pembayaran">
    <x-page-header title="Terima Pembayaran"
                   subtitle="Tombol alokasi otomatis mengisi tagihan tertua lebih dulu, termasuk diskon pelunasan dini."
                   :back="route('finance.receipts.index')" />

    @livewire('finance.receipt-form', ['customerId' => $customerId])
</x-layouts.app>
