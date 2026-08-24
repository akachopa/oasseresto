<x-layouts.app title="Invoice Pembelian">
    <x-page-header title="Invoice Pembelian"
                   subtitle="Tagihan supplier atas barang yang sudah diterima. Posting invoice membentuk hutang.">
        <x-slot:actions>
            @can('purchase.create')
                <a href="{{ route('purchase.invoices.create') }}" wire:navigate class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" /> Buat Invoice
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-purchase-invoices"
        :url="route('purchase.invoices.data')"
        :order="[[2, 'desc']]"
        :columns="[
            ['data' => 'number', 'title' => 'Nomor'],
            ['data' => 'date', 'title' => 'Tanggal'],
            ['data' => 'supplier', 'title' => 'Supplier'],
            ['data' => 'due', 'title' => 'Jatuh Tempo'],
            ['data' => 'total', 'title' => 'Total', 'className' => 'text-right'],
            ['data' => 'outstanding', 'title' => 'Sisa Hutang', 'className' => 'text-right'],
            ['data' => 'status', 'title' => 'Status'],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-pi-supplier">Supplier</label>
                <select id="filter-pi-supplier" name="supplier" data-table-filter="tbl-purchase-invoices" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($suppliers as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-pi-status">Status</label>
                <select id="filter-pi-status" name="status" data-table-filter="tbl-purchase-invoices" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-pi-overdue">Jatuh Tempo</label>
                <select id="filter-pi-overdue" name="overdue" data-table-filter="tbl-purchase-invoices" class="input w-auto">
                    <option value="">Semua</option>
                    <option value="1">Hanya yang terlambat</option>
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
