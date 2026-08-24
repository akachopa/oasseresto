<x-layouts.app title="Audit Trail">
    <x-page-header title="Audit Trail" subtitle="Jejak login, perubahan dokumen, approval, dan posting." />

    <x-data-table
        id="tbl-audit"
        :url="route('team.audit.data')"
        :order="[[1, 'desc']]"
        :columns="[
            ['data' => 'created_at', 'title' => 'Waktu'],
            ['data' => 'event', 'title' => 'Aksi'],
            ['data' => 'description', 'title' => 'Keterangan'],
            ['data' => 'subject', 'title' => 'Objek'],
            ['data' => 'causer', 'title' => 'Oleh', 'orderable' => false],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-audit-event">Aksi</label>
                <select id="filter-audit-event" name="event" data-table-filter="tbl-audit" class="input w-auto">
                    <option value="">Semua</option>
                    @foreach (['login', 'logout', 'created', 'updated', 'deleted', 'submitted', 'approved', 'posted', 'reversed', 'permission_changed'] as $event)
                        <option value="{{ $event }}">{{ $event }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-audit-from">Dari</label>
                <input id="filter-audit-from" name="from" type="date" data-table-filter="tbl-audit" class="input w-auto">
            </div>

            <div>
                <label class="label" for="filter-audit-to">Sampai</label>
                <input id="filter-audit-to" name="to" type="date" data-table-filter="tbl-audit" class="input w-auto">
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
