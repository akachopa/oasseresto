<x-layouts.app title="Penawaran">
    <x-page-header title="Penawaran"
                   subtitle="Harga yang dijanjikan ke customer. Penawaran yang sudah dikirim bisa langsung jadi sales order.">
        <x-slot:actions>
            @can('sales.create')
                <a href="{{ route('sales.quotations.create') }}" wire:navigate class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" /> Buat Penawaran
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-quotations"
        :url="route('sales.quotations.data')"
        :order="[[2, 'desc']]"
        :columns="[
            ['data' => 'number', 'title' => 'Nomor'],
            ['data' => 'date', 'title' => 'Tanggal'],
            ['data' => 'customer', 'title' => 'Customer'],
            ['data' => 'valid', 'title' => 'Berlaku Sampai'],
            ['data' => 'total', 'title' => 'Total', 'className' => 'text-right'],
            ['data' => 'status', 'title' => 'Status'],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-qt-customer">Customer</label>
                <select id="filter-qt-customer" name="customer" data-table-filter="tbl-quotations" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($customers as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-qt-status">Status</label>
                <select id="filter-qt-status" name="status" data-table-filter="tbl-quotations" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
