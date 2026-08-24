<x-layouts.app title="Permintaan Approval">
    <x-page-header title="Permintaan Approval"
                   subtitle="Riwayat dan antrian approval seluruh dokumen." />

    <x-data-table
        id="tbl-approval-requests"
        :url="route('approval.requests.data')"
        :order="[[1, 'desc']]"
        :columns="[
            ['data' => 'requested_at', 'title' => 'Diajukan'],
            ['data' => 'document', 'title' => 'Jenis'],
            ['data' => 'number', 'title' => 'Nomor'],
            ['data' => 'title', 'title' => 'Judul', 'orderable' => false],
            ['data' => 'amount', 'title' => 'Nilai', 'className' => 'text-right'],
            ['data' => 'status', 'title' => 'Status'],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-req-type">Dokumen</label>
                <select id="filter-req-type" name="document_type" data-table-filter="tbl-approval-requests" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($documentTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="filter-req-status">Status</label>
                <select id="filter-req-status" name="status" data-table-filter="tbl-approval-requests" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
