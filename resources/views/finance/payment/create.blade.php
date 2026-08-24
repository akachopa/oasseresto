<x-layouts.app title="Bayar Hutang">
    <x-page-header title="Bayar Hutang"
                   subtitle="Alokasi otomatis memprioritaskan hutang yang paling dekat jatuh tempo."
                   :back="route('finance.payments.index')" />

    @livewire('finance.payment-form', ['supplierId' => $supplierId])
</x-layouts.app>
