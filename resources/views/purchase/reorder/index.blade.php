<x-layouts.app title="Rekomendasi Reorder">
    <x-page-header title="Rekomendasi Reorder"
                   subtitle="Barang dengan proyeksi stok di bawah titik reorder. Barang dalam pesanan sudah diperhitungkan.">
        @can('purchase.create')
            <x-slot:actions>
                <form method="POST" action="{{ route('purchase.reorder.store') }}"
                      class="flex flex-wrap items-end gap-2">
                    @csrf

                    <div>
                        <label class="label" for="reorder-warehouse">Gudang Tujuan</label>
                        <select id="reorder-warehouse" name="warehouse_id" class="input w-auto" required>
                            <option value="">Pilih gudang</option>
                            @foreach ($warehouses as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="btn-primary">
                        <x-icon name="plus" class="h-4 w-4" /> Buat Purchase Request
                    </button>
                </form>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-data-table
        id="tbl-reorder"
        :url="route('purchase.reorder.data')"
        :order="[[5, 'asc']]"
        :columns="[
            ['data' => 'sku', 'title' => 'SKU'],
            ['data' => 'name', 'title' => 'Produk'],
            ['data' => 'on_hand', 'title' => 'Stok', 'className' => 'text-right'],
            ['data' => 'incoming', 'title' => 'Dalam Pesanan', 'className' => 'text-right'],
            ['data' => 'available', 'title' => 'Proyeksi', 'className' => 'text-right'],
            ['data' => 'reorder_point', 'title' => 'Titik Reorder', 'className' => 'text-right'],
            ['data' => 'supplier', 'title' => 'Supplier Pilihan'],
            ['data' => 'suggestion', 'title' => 'Usulan Beli', 'className' => 'text-right'],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-reorder-warehouse">Gudang</label>
                <select id="filter-reorder-warehouse" name="warehouse" data-table-filter="tbl-reorder" class="input w-auto">
                    <option value="">Semua gudang</option>
                    @foreach ($warehouses as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
