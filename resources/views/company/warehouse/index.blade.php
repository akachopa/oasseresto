<x-layouts.app title="Gudang">
    <x-page-header title="Gudang" subtitle="Gudang utama, outlet, transit, dan gudang barang rusak.">
        <x-slot:actions>
            <a href="{{ route('settings.warehouses.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" /> Tambah Gudang
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-warehouses"
        :url="route('settings.warehouses.data')"
        :order="[[1, 'asc']]"
        :columns="[
            ['data' => 'code', 'title' => 'Kode'],
            ['data' => 'name', 'title' => 'Nama Gudang'],
            ['data' => 'branch', 'title' => 'Cabang'],
            ['data' => 'type', 'title' => 'Tipe'],
            ['data' => 'sellable', 'title' => 'Penjualan', 'orderable' => false],
            ['data' => 'status', 'title' => 'Status', 'orderable' => false],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-wh-branch">Cabang</label>
                <select id="filter-wh-branch" name="branch_id" data-table-filter="tbl-warehouses" class="input w-auto">
                    <option value="">Semua Cabang</option>
                    @foreach (\App\Modules\Company\Models\Branch::active()->orderBy('name')->pluck('name', 'id') as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-wh-active">Status</label>
                <select id="filter-wh-active" name="is_active" data-table-filter="tbl-warehouses" class="input w-auto">
                    <option value="">Semua</option>
                    <option value="1">Aktif</option>
                    <option value="0">Nonaktif</option>
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
