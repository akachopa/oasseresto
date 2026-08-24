<x-layouts.app title="Jurnal">
    <x-page-header title="Jurnal Umum"
                   subtitle="Jurnal hanya terbentuk dari posting dokumen atau jurnal manual lewat service, bukan insert langsung.">
        <x-slot:actions>
            @can('accounting.journal.create')
                <a href="{{ route('accounting.journals.create') }}" wire:navigate class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" /> Jurnal Manual
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-journals"
        :url="route('accounting.journals.data')"
        :order="[[2, 'desc']]"
        :columns="[
            ['data' => 'number', 'title' => 'Nomor'],
            ['data' => 'date', 'title' => 'Tanggal'],
            ['data' => 'description', 'title' => 'Keterangan'],
            ['data' => 'document', 'title' => 'Dokumen'],
            ['data' => 'amount', 'title' => 'Nilai', 'className' => 'text-right'],
            ['data' => 'status', 'title' => 'Status'],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-jurnal-status">Status</label>
                <select id="filter-jurnal-status" name="status" data-table-filter="tbl-journals" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="filter-jurnal-source">Sumber</label>
                <select id="filter-jurnal-source" name="source" data-table-filter="tbl-journals" class="input w-auto">
                    <option value="">Semua</option>
                    <option value="system">Sistem</option>
                    <option value="manual">Manual</option>
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
