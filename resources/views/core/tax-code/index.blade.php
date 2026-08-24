<x-layouts.app title="Kode Pajak">
    <x-page-header title="Kode Pajak" subtitle="Tax engine dasar untuk penjualan dan pembelian.">
        <x-slot:actions>
            <a href="{{ route('settings.tax-codes.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" /> Tambah Kode Pajak
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-tax-codes"
        :url="route('settings.tax-codes.data')"
        :order="[[1, 'asc']]"
        :columns="[
            ['data' => 'code', 'title' => 'Kode'],
            ['data' => 'name', 'title' => 'Nama'],
            ['data' => 'rate', 'title' => 'Tarif'],
            ['data' => 'inclusive', 'title' => 'Perlakuan', 'orderable' => false],
            ['data' => 'usage', 'title' => 'Dipakai Untuk', 'orderable' => false],
            ['data' => 'status', 'title' => 'Status', 'orderable' => false],
        ]" />
</x-layouts.app>
