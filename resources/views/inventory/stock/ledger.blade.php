<x-layouts.app title="Kartu Stok">
    <x-page-header title="Kartu Stok" subtitle="Riwayat pergerakan stok. Baris di sini tidak pernah diubah, hanya ditambah."
                   :back="route('inventory.stock.index')" />

    <x-data-table
        id="tbl-ledger"
        :url="route('inventory.ledger.data')"
        :order="[[1, 'desc']]"
        :columns="[
            ['data' => 'date', 'title' => 'Waktu'],
            ['data' => 'sku', 'title' => 'SKU'],
            ['data' => 'product', 'title' => 'Produk'],
            ['data' => 'warehouse', 'title' => 'Gudang'],
            ['data' => 'type', 'title' => 'Jenis'],
            ['data' => 'quantity', 'title' => 'Gerakan', 'className' => 'text-right'],
            ['data' => 'unit_cost', 'title' => 'HPP', 'className' => 'text-right'],
            ['data' => 'balance', 'title' => 'Saldo', 'className' => 'text-right'],
            ['data' => 'document', 'title' => 'Dokumen', 'orderable' => false],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-ledger-warehouse">Gudang</label>
                <select id="filter-ledger-warehouse" name="warehouse" data-table-filter="tbl-ledger" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($warehouses as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-ledger-type">Jenis</label>
                <select id="filter-ledger-type" name="type" data-table-filter="tbl-ledger" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-ledger-from">Dari</label>
                <input id="filter-ledger-from" type="date" name="from" data-table-filter="tbl-ledger" class="input w-auto">
            </div>

            <div>
                <label class="label" for="filter-ledger-to">Sampai</label>
                <input id="filter-ledger-to" type="date" name="to" data-table-filter="tbl-ledger" class="input w-auto">
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
