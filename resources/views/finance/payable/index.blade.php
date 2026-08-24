@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Hutang">
    <x-page-header title="Hutang"
                   subtitle="Daftar kewajiban ke supplier beserta jadwal jatuh temponya.">
        <x-slot:actions>
            <a href="{{ route('finance.payables.aging') }}" wire:navigate class="btn-secondary">
                <x-icon name="chart" class="h-4 w-4" /> Umur Hutang
            </a>

            @can('finance.payable.pay')
                <a href="{{ route('finance.payments.create') }}" wire:navigate class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" /> Bayar Hutang
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Total Hutang" :value="Money::compact($outstandingTotal)"
               :hint="$outstandingCount.' dokumen'" icon="arrow-up" />
        <x-kpi label="Jatuh Tempo 7 Hari" :value="Money::compact($dueSoonTotal)"
               :hint="$dueSoonCount.' dokumen'" :tone="$dueSoonTotal > 0 ? 'caution' : 'positive'" />
    </div>

    <div class="mt-4">
        <x-data-table
            id="tbl-payables"
            :url="route('finance.payables.data')"
            :order="[[4, 'asc']]"
            :columns="[
                ['data' => 'document', 'title' => 'Dokumen'],
                ['data' => 'date', 'title' => 'Tanggal'],
                ['data' => 'supplier', 'title' => 'Supplier'],
                ['data' => 'due', 'title' => 'Jatuh Tempo'],
                ['data' => 'amount', 'title' => 'Nilai', 'className' => 'text-right'],
                ['data' => 'outstanding', 'title' => 'Sisa', 'className' => 'text-right'],
                ['data' => 'status', 'title' => 'Status'],
            ]">
            <x-slot:filters>
                <div>
                    <label class="label" for="filter-ap-supplier">Supplier</label>
                    <select id="filter-ap-supplier" name="supplier" data-table-filter="tbl-payables" class="input w-auto">
                        <option value="">Semua</option>
                        @foreach ($suppliers as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label" for="filter-ap-status">Status</label>
                    <select id="filter-ap-status" name="status" data-table-filter="tbl-payables" class="input w-auto">
                        <option value="">Semua</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label" for="filter-ap-due">Tampilkan</label>
                    <select id="filter-ap-due" name="due_soon" data-table-filter="tbl-payables" class="input w-auto">
                        <option value="">Semua</option>
                        <option value="1">Jatuh tempo 7 hari</option>
                    </select>
                </div>
            </x-slot:filters>
        </x-data-table>
    </div>
</x-layouts.app>
