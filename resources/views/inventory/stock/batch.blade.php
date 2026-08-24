<x-layouts.app title="Batch & Kedaluwarsa">
    <x-page-header title="Batch & Kedaluwarsa"
                   subtitle="Barang keluar mengikuti FEFO: batch yang paling dekat kedaluwarsa lebih dulu."
                   :back="route('inventory.stock.index')" />

    <x-data-table
        id="tbl-batches"
        :url="route('inventory.batches.data')"
        :order="[[1, 'asc']]"
        :columns="[
            ['data' => 'expiry', 'title' => 'Kedaluwarsa'],
            ['data' => 'batch_number', 'title' => 'Batch'],
            ['data' => 'product', 'title' => 'Produk'],
            ['data' => 'warehouse', 'title' => 'Gudang'],
            ['data' => 'quantity', 'title' => 'Sisa', 'className' => 'text-right'],
            ['data' => 'unit_cost', 'title' => 'HPP', 'className' => 'text-right'],
            ['data' => 'value', 'title' => 'Nilai', 'className' => 'text-right', 'orderable' => false],
            ['data' => 'received', 'title' => 'Diterima', 'orderable' => false],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-batch-warehouse">Gudang</label>
                <select id="filter-batch-warehouse" name="warehouse" data-table-filter="tbl-batches" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($warehouses as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-batch-condition">Kondisi</label>
                <select id="filter-batch-condition" name="condition" data-table-filter="tbl-batches" class="input w-auto">
                    <option value="">Semua</option>
                    <option value="available">Masih ada</option>
                    <option value="near_expiry">Mendekati kedaluwarsa</option>
                    <option value="expired">Sudah kedaluwarsa</option>
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
