<x-layouts.app title="Penerimaan Pembayaran">
    <x-page-header title="Penerimaan Pembayaran"
                   subtitle="Uang masuk selalu dialokasikan ke tagihan tertentu sebelum diposting.">
        <x-slot:actions>
            <a href="{{ route('finance.receipts.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" /> Terima Pembayaran
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-receipts"
        :url="route('finance.receipts.data')"
        :order="[[2, 'desc']]"
        :columns="[
            ['data' => 'number', 'title' => 'Nomor'],
            ['data' => 'date', 'title' => 'Tanggal'],
            ['data' => 'customer', 'title' => 'Customer'],
            ['data' => 'account', 'title' => 'Kas/Bank'],
            ['data' => 'method', 'title' => 'Metode'],
            ['data' => 'amount', 'title' => 'Nilai', 'className' => 'text-right'],
            ['data' => 'status', 'title' => 'Status'],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-rcp-customer">Customer</label>
                <select id="filter-rcp-customer" name="customer" data-table-filter="tbl-receipts" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($customers as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-rcp-method">Metode</label>
                <select id="filter-rcp-method" name="method" data-table-filter="tbl-receipts" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($methods as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-rcp-status">Status</label>
                <select id="filter-rcp-status" name="status" data-table-filter="tbl-receipts" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
