<x-layouts.app title="Supplier">
    <x-page-header title="Supplier" subtitle="Pemasok beserta termin, lead time, dan performa pengiriman.">
        <x-slot:actions>
            <a href="{{ route('suppliers.performance') }}" wire:navigate class="btn-secondary">
                <x-icon name="chart" class="h-4 w-4" /> Performa
            </a>
            @can('supplier.create')
                <a href="{{ route('suppliers.create') }}" wire:navigate class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" /> Tambah Supplier
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-suppliers"
        :url="route('suppliers.data')"
        :order="[[2, 'asc']]"
        :columns="[
            ['data' => 'code', 'title' => 'Kode'],
            ['data' => 'name', 'title' => 'Nama Supplier'],
            ['data' => 'city', 'title' => 'Kota'],
            ['data' => 'payment_term', 'title' => 'Termin'],
            ['data' => 'lead_time', 'title' => 'Lead Time'],
            ['data' => 'outstanding', 'title' => 'Hutang', 'className' => 'text-right'],
            ['data' => 'performance', 'title' => 'Performa'],
            ['data' => 'status', 'title' => 'Status', 'orderable' => false],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-supplier-term">Termin</label>
                <select id="filter-supplier-term" name="payment_term" data-table-filter="tbl-suppliers" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($paymentTerms as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-supplier-active">Status</label>
                <select id="filter-supplier-active" name="is_active" data-table-filter="tbl-suppliers" class="input w-auto">
                    <option value="">Semua</option>
                    <option value="1">Aktif</option>
                    <option value="0">Nonaktif</option>
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
