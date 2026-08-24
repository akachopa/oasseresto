<x-layouts.app title="Chart of Accounts">
    <x-page-header title="Chart of Accounts" subtitle="Pemetaan jurnal memakai slug akun, bukan nomor yang bisa diubah.">
        <x-slot:actions>
            @can('accounting.coa.manage')
                <a href="{{ route('accounting.accounts.create') }}" wire:navigate class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" /> Tambah Akun
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-accounts"
        :url="route('accounting.accounts.data')"
        :order="[[1, 'asc']]"
        :columns="[
            ['data' => 'code', 'title' => 'Kode'],
            ['data' => 'name', 'title' => 'Nama'],
            ['data' => 'type', 'title' => 'Tipe'],
            ['data' => 'slug', 'title' => 'Slug'],
            ['data' => 'postable', 'title' => 'Postable', 'orderable' => false],
            ['data' => 'status', 'title' => 'Status', 'orderable' => false],
        ]" />
</x-layouts.app>
