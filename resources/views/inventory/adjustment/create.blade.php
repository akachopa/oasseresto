<x-layouts.app title="Buat Penyesuaian Stok">
    <x-page-header title="Buat Penyesuaian Stok"
                   subtitle="Pakai kuantitas minus untuk mengurangi stok dan plus untuk menambah."
                   :back="route('inventory.adjustments.index')" />

    @livewire('inventory.adjustment-form')
</x-layouts.app>
