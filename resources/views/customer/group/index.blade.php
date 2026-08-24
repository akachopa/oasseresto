<x-layouts.app title="Grup Customer">
    <x-page-header title="Grup Customer" subtitle="Grup menentukan default level harga, termin, dan credit limit.">
        <x-slot:actions>
            @can('customer.edit')
                <a href="{{ route('customer-groups.create') }}" wire:navigate class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" /> Tambah Grup
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-customer-groups"
        :url="route('customer-groups.data')"
        :order="[[1, 'asc']]"
        :columns="[
            ['data' => 'code', 'title' => 'Kode'],
            ['data' => 'name', 'title' => 'Nama Grup'],
            ['data' => 'price_level', 'title' => 'Level Harga', 'orderable' => false],
            ['data' => 'payment_term', 'title' => 'Termin Default'],
            ['data' => 'credit_limit', 'title' => 'Credit Limit Default', 'className' => 'text-right'],
            ['data' => 'customers', 'title' => 'Customer', 'orderable' => false],
            ['data' => 'status', 'title' => 'Status', 'orderable' => false],
        ]" />
</x-layouts.app>
