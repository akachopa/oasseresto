<x-layouts.app title="Aturan Approval">
    <x-page-header title="Aturan Approval"
                   subtitle="Siapa yang menyetujui ditentukan di sini, bukan di kode modul.">
        <x-slot:actions>
            <a href="{{ route('approval.rules.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" /> Tambah Aturan
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-approval-rules"
        :url="route('approval.rules.data')"
        :order="[[1, 'asc']]"
        :columns="[
            ['data' => 'document', 'title' => 'Dokumen'],
            ['data' => 'name', 'title' => 'Nama'],
            ['data' => 'sequence', 'title' => 'Urutan'],
            ['data' => 'amount', 'title' => 'Nilai'],
            ['data' => 'approver', 'title' => 'Penyetuju', 'orderable' => false],
            ['data' => 'status', 'title' => 'Status', 'orderable' => false],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-rule-type">Dokumen</label>
                <select id="filter-rule-type" name="document_type" data-table-filter="tbl-approval-rules" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach (\App\Modules\Approval\Controllers\ApprovalRuleController::documentTypes() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
