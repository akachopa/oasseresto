<x-layouts.app title="Level Harga">
    <x-page-header title="Level Harga" subtitle="Tingkatan harga jual, misalnya grosir, semi grosir, dan retail.">
        <x-slot:actions>
            <a href="{{ route('price-levels.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" /> Tambah Level
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-price-levels"
        :url="route('price-levels.data')"
        :order="[[3, 'asc']]"
        :columns="[
            ['data' => 'code', 'title' => 'Kode'],
            ['data' => 'name', 'title' => 'Nama Level'],
            ['data' => 'sequence', 'title' => 'Urutan'],
            ['data' => 'default', 'title' => 'Default', 'orderable' => false],
            ['data' => 'status', 'title' => 'Status', 'orderable' => false],
        ]" />
</x-layouts.app>
