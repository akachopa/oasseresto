<x-layouts.app :title="'Ubah '.$supplier->name">
    <x-page-header :title="'Ubah '.$supplier->name" :subtitle="$supplier->code" :back="route('suppliers.index')" />

    @livewire('supplier.supplier-form', ['supplier' => $supplier])
</x-layouts.app>
