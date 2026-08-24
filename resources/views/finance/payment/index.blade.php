<x-layouts.app title="Pembayaran Hutang">
    <x-page-header title="Pembayaran Hutang"
                   subtitle="Kas hanya berkurang untuk hutang yang benar-benar ada, jadi pembayaran wajib dialokasikan.">
        <x-slot:actions>
            <a href="{{ route('finance.payments.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" /> Bayar Hutang
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-payments"
        :url="route('finance.payments.data')"
        :order="[[2, 'desc']]"
        :columns="[
            ['data' => 'number', 'title' => 'Nomor'],
            ['data' => 'date', 'title' => 'Tanggal'],
            ['data' => 'supplier', 'title' => 'Supplier'],
            ['data' => 'account', 'title' => 'Kas/Bank'],
            ['data' => 'method', 'title' => 'Metode'],
            ['data' => 'amount', 'title' => 'Nilai', 'className' => 'text-right'],
            ['data' => 'status', 'title' => 'Status'],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-pay-supplier">Supplier</label>
                <select id="filter-pay-supplier" name="supplier" data-table-filter="tbl-payments" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($suppliers as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-pay-method">Metode</label>
                <select id="filter-pay-method" name="method" data-table-filter="tbl-payments" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($methods as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-pay-status">Status</label>
                <select id="filter-pay-status" name="status" data-table-filter="tbl-payments" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
