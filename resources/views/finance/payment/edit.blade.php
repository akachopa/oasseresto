<x-layouts.app :title="'Ubah '.$payment->number">
    <x-page-header :title="'Ubah '.$payment->number"
                   subtitle="Pembayaran hanya bisa diubah selama belum diposting."
                   :back="route('finance.payments.detail', $payment)" />

    @livewire('finance.payment-form', ['payment' => $payment])
</x-layouts.app>
