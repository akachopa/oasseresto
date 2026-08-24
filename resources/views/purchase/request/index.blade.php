<x-layouts.app title="Purchase Request">
    <x-page-header title="Purchase Request"
                   subtitle="Permintaan barang dari gudang; belum jadi komitmen ke supplier sampai diterbitkan sebagai PO.">
        <x-slot:actions>
            <a href="{{ route('purchase.reorder.index') }}" wire:navigate class="btn-secondary">
                <x-icon name="sparkles" class="h-4 w-4" /> Rekomendasi
            </a>

            @can('purchase.create')
                <a href="{{ route('purchase.requests.create') }}" wire:navigate class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" /> Buat Purchase Request
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-purchase-requests"
        :url="route('purchase.requests.data')"
        :order="[[2, 'desc']]"
        :columns="[
            ['data' => 'number', 'title' => 'Nomor'],
            ['data' => 'date', 'title' => 'Tanggal'],
            ['data' => 'warehouse', 'title' => 'Gudang'],
            ['data' => 'needed', 'title' => 'Dibutuhkan'],
            ['data' => 'estimate', 'title' => 'Estimasi', 'className' => 'text-right'],
            ['data' => 'status', 'title' => 'Status'],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-pr-warehouse">Gudang</label>
                <select id="filter-pr-warehouse" name="warehouse" data-table-filter="tbl-purchase-requests" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($warehouses as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-pr-priority">Prioritas</label>
                <select id="filter-pr-priority" name="priority" data-table-filter="tbl-purchase-requests" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($priorities as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-pr-status">Status</label>
                <select id="filter-pr-status" name="status" data-table-filter="tbl-purchase-requests" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
