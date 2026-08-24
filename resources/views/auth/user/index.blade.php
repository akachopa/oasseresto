<x-layouts.app title="User">
    <x-page-header title="User" subtitle="Kelola akses tim berdasarkan role dan lokasi.">
        <x-slot:actions>
            <a href="{{ route('team.users.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" /> Tambah User
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-data-table
        id="tbl-users"
        :url="route('team.users.data')"
        :order="[[1, 'asc']]"
        :columns="[
            ['data' => 'name', 'title' => 'Nama'],
            ['data' => 'email', 'title' => 'Email / Username'],
            ['data' => 'roles', 'title' => 'Role', 'orderable' => false],
            ['data' => 'scope', 'title' => 'Scope Lokasi', 'orderable' => false],
            ['data' => 'branch', 'title' => 'Cabang Default'],
            ['data' => 'last_login', 'title' => 'Login Terakhir'],
        ]">
        <x-slot:filters>
            <div>
                <label class="label" for="filter-user-role">Role</label>
                <select id="filter-user-role" name="role" data-table-filter="tbl-users" class="input w-auto">
                    <option value="">Semua Role</option>
                    @foreach (array_keys(\App\Modules\Auth\Support\RoleTemplate::all()) as $role)
                        <option value="{{ $role }}">{{ $role }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-user-active">Status</label>
                <select id="filter-user-active" name="is_active" data-table-filter="tbl-users" class="input w-auto">
                    <option value="">Semua</option>
                    <option value="1">Aktif</option>
                    <option value="0">Nonaktif</option>
                </select>
            </div>
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
