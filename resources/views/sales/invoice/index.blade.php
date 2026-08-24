<x-layouts.app title="Invoice Penjualan">
    <x-page-header title="Invoice Penjualan"
                   subtitle="Tagihan atas barang yang sudah dikirim. Posting invoice membentuk piutang customer.">
        <x-slot:actions>
            @can('sales.create')
                <a href="{{ route('sales.invoices.create') }}" wire:navigate class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" /> Buat Invoice
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-sales-invoices"
        :url="route('sales.invoices.data')"
        :order="[[2, 'desc']]"
        :columns="[
            ['data' => 'number', 'title' => 'Nomor'],
            ['data' => 'date', 'title' => 'Tanggal'],
            ['data' => 'customer', 'title' => 'Customer'],
            ['data' => 'due', 'title' => 'Jatuh Tempo'],
            ['data' => 'total', 'title' => 'Total', 'className' => 'text-right'],
            ['data' => 'outstanding', 'title' => 'Sisa Piutang', 'className' => 'text-right'],
            ['data' => 'status', 'title' => 'Status'],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-si-customer">Customer</label>
                <select id="filter-si-customer" name="customer" data-table-filter="tbl-sales-invoices" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($customers as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-si-status">Status</label>
                <select id="filter-si-status" name="status" data-table-filter="tbl-sales-invoices" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-si-source">Sumber</label>
                <select id="filter-si-source" name="source" data-table-filter="tbl-sales-invoices" class="input w-auto">
                    <option value="">Semua</option>
                    <option value="manual">Sales order</option>
                    <option value="pos">Kasir (POS)</option>
                </select>
            </div>

            <div>
                <label class="label" for="filter-si-overdue">Jatuh Tempo</label>
                <select id="filter-si-overdue" name="overdue" data-table-filter="tbl-sales-invoices" class="input w-auto">
                    <option value="">Semua</option>
                    <option value="1">Hanya yang terlambat</option>
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
