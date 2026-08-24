<x-layouts.app :title="'Ubah '.$customer->name">
    <x-page-header :title="'Ubah '.$customer->name" :subtitle="$customer->code" :back="route('customers.index')" />

    @livewire('customer.customer-form', ['customer' => $customer])
</x-layouts.app>
