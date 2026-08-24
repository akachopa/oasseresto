<x-layouts.app title="Satuan">
    <x-page-header title="Satuan" subtitle="Satuan dasar dan satuan turunan untuk konversi stok.">
        <x-slot:actions>
            <a href="{{ route('units.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" /> Tambah Satuan
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-units"
        :url="route('units.data')"
        :order="[[1, 'asc']]"
        :columns="[
            ['data' => 'code', 'title' => 'Kode'],
            ['data' => 'name', 'title' => 'Nama'],
            ['data' => 'category', 'title' => 'Jenis'],
            ['data' => 'status', 'title' => 'Status', 'orderable' => false],
        ]" />
</x-layouts.app>
