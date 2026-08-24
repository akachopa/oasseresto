@php
    use App\Modules\Core\Support\Money;
@endphp

<x-layouts.app title="Piutang">
    <x-page-header title="Piutang"
                   subtitle="Sisa tagihan dihitung dari dokumen sumbernya, jadi selalu sinkron dengan invoice.">
        <x-slot:actions>
            <a href="{{ route('finance.receivables.aging') }}" wire:navigate class="btn-secondary">
                <x-icon name="chart" class="h-4 w-4" /> Umur Piutang
            </a>

            @can('finance.receivable.collect')
                <a href="{{ route('finance.receipts.create') }}" wire:navigate class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" /> Terima Pembayaran
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-kpi label="Total Piutang" :value="Money::compact($outstandingTotal)"
               :hint="$outstandingCount.' dokumen'" icon="arrow-down" />
        <x-kpi label="Jatuh Tempo" :value="Money::compact($overdueTotal)"
               :hint="$overdueCount.' dokumen'" :tone="$overdueTotal > 0 ? 'negative' : 'positive'" />
    </div>

    <div class="mt-4">
        <x-data-table
            id="tbl-receivables"
            :url="route('finance.receivables.data')"
            :order="[[4, 'asc']]"
            :columns="[
                ['data' => 'document', 'title' => 'Dokumen'],
                ['data' => 'date', 'title' => 'Tanggal'],
                ['data' => 'customer', 'title' => 'Customer'],
                ['data' => 'due', 'title' => 'Jatuh Tempo'],
                ['data' => 'amount', 'title' => 'Nilai', 'className' => 'text-right'],
                ['data' => 'outstanding', 'title' => 'Sisa', 'className' => 'text-right'],
                ['data' => 'status', 'title' => 'Status'],
            ]">
            <x-slot:filters>
                <div>
                    <label class="label" for="filter-ar-customer">Customer</label>
                    <select id="filter-ar-customer" name="customer" data-table-filter="tbl-receivables" class="input w-auto">
                        <option value="">Semua</option>
                        @foreach ($customers as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label" for="filter-ar-status">Status</label>
                    <select id="filter-ar-status" name="status" data-table-filter="tbl-receivables" class="input w-auto">
                        <option value="">Semua</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label" for="filter-ar-overdue">Tampilkan</label>
                    <select id="filter-ar-overdue" name="overdue" data-table-filter="tbl-receivables" class="input w-auto">
                        <option value="">Semua</option>
                        <option value="1">Hanya jatuh tempo</option>
                    </select>
                </div>
            </x-slot:filters>
        </x-data-table>
    </div>
</x-layouts.app>
