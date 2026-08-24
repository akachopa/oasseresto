<x-layouts.app :title="'Ubah '.$adjustment->number">
    <x-page-header :title="'Ubah '.$adjustment->number" subtitle="Draft masih bisa diubah sebelum diposting."
                   :back="route('inventory.adjustments.detail', $adjustment)" />

    @livewire('inventory.adjustment-form', ['adjustment' => $adjustment])
</x-layouts.app>
