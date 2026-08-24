<x-layouts.app title="Aturan Harga">
    <x-page-header title="Aturan Harga"
                   subtitle="Aturan paling spesifik dipakai lebih dulu: customer, grup, tier kuantitas, lalu level harga.">
        <x-slot:actions>
            <a href="{{ route('price-rules.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" /> Tambah Aturan
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-price-rules"
        :url="route('price-rules.data')"
        :order="[[1, 'asc']]"
        :columns="[
            ['data' => 'name', 'title' => 'Nama Aturan'],
            ['data' => 'type', 'title' => 'Jenis'],
            ['data' => 'target', 'title' => 'Produk', 'orderable' => false],
            ['data' => 'applies_to', 'title' => 'Berlaku Untuk', 'orderable' => false],
            ['data' => 'quantity', 'title' => 'Kuantitas'],
            ['data' => 'period', 'title' => 'Periode'],
            ['data' => 'value', 'title' => 'Nilai', 'orderable' => false],
            ['data' => 'status', 'title' => 'Status', 'orderable' => false],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-rule-type">Jenis</label>
                <select id="filter-rule-type" name="type" data-table-filter="tbl-price-rules" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-rule-active">Status</label>
                <select id="filter-rule-active" name="is_active" data-table-filter="tbl-price-rules" class="input w-auto">
                    <option value="">Semua</option>
                    <option value="1">Aktif</option>
                    <option value="0">Nonaktif</option>
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
