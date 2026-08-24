<x-layouts.app title="Tambah Customer">
    <x-page-header title="Tambah Customer" subtitle="Grup customer menentukan default level harga dan termin."
                   :back="route('customers.index')" />

    @livewire('customer.customer-form')
</x-layouts.app>
