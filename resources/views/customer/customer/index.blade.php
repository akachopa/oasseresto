<x-layouts.app title="Customer">
    <x-page-header title="Customer" subtitle="Data pelanggan beserta plafon kredit dan piutang berjalan.">
        <x-slot:actions>
            <x-export-buttons type="customers" />
            @can('customer.create')
                <a href="{{ route('customers.create') }}" wire:navigate class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" /> Tambah Customer
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-customers"
        :url="route('customers.data')"
        :order="[[2, 'asc']]"
        :columns="[
            ['data' => 'code', 'title' => 'Kode'],
            ['data' => 'name', 'title' => 'Nama Customer'],
            ['data' => 'group', 'title' => 'Grup'],
            ['data' => 'city', 'title' => 'Kota'],
            ['data' => 'salesman', 'title' => 'Salesman'],
            ['data' => 'credit_limit', 'title' => 'Credit Limit', 'className' => 'text-right'],
            ['data' => 'outstanding', 'title' => 'Piutang', 'className' => 'text-right'],
            ['data' => 'status', 'title' => 'Status', 'orderable' => false],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-customer-group">Grup</label>
                <select id="filter-customer-group" name="group" data-table-filter="tbl-customers" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($groups as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-customer-salesman">Salesman</label>
                <select id="filter-customer-salesman" name="salesman" data-table-filter="tbl-customers" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($salesmen as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-customer-overdue">Piutang</label>
                <select id="filter-customer-overdue" name="overdue" data-table-filter="tbl-customers" class="input w-auto">
                    <option value="">Semua</option>
                    <option value="1">Ada yang jatuh tempo</option>
                </select>
            </div>

            <div>
                <label class="label" for="filter-customer-active">Status</label>
                <select id="filter-customer-active" name="is_active" data-table-filter="tbl-customers" class="input w-auto">
                    <option value="">Semua</option>
                    <option value="1">Aktif</option>
                    <option value="0">Nonaktif</option>
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
