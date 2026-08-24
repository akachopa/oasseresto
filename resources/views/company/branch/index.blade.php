<x-layouts.app title="Cabang">
    <x-page-header title="Cabang" subtitle="Kelola cabang dan outlet perusahaan.">
        <x-slot:actions>
            <a href="{{ route('settings.branches.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" /> Tambah Cabang
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-branches"
        :url="route('settings.branches.data')"
        :order="[[1, 'asc']]"
        :columns="[
            ['data' => 'code', 'title' => 'Kode'],
            ['data' => 'name', 'title' => 'Nama Cabang'],
            ['data' => 'city', 'title' => 'Kota'],
            ['data' => 'business_unit', 'title' => 'Business Unit'],
            ['data' => 'warehouses', 'title' => 'Gudang', 'orderable' => false],
            ['data' => 'status', 'title' => 'Status', 'orderable' => false],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-branch-active">Status</label>
                <select id="filter-branch-active" name="is_active" data-table-filter="tbl-branches" class="input w-auto">
                    <option value="">Semua</option>
                    <option value="1">Aktif</option>
                    <option value="0">Nonaktif</option>
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
