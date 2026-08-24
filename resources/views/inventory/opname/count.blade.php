<x-layouts.app :title="'Hitung '.$opname->number">
    <x-page-header :title="$opname->number"
                   :subtitle="$opname->warehouse?->name.' · '.$opname->opname_date->format('d/m/Y')"
                   :back="route('inventory.opnames.index')" />

    @livewire('inventory.opname-count', ['opname' => $opname])
</x-layouts.app>
