<x-layouts.app :title="$user->exists ? 'Ubah User' : 'Tambah User'">
    <x-page-header :title="$user->exists ? 'Ubah User: '.$user->name : 'Tambah User'"
                   subtitle="Role menentukan apa yang boleh dikerjakan, scope menentukan di lokasi mana."
                   :back="route('team.users.index')" />

    @php
        $scopeLevel = old('scope_level', $currentScopes->first()?->level?->value ?? 'company');
        $selectedBranches = old('scope_branches', $currentScopes->pluck('branch_id')->filter()->all());
        $selectedWarehouses = old('scope_warehouses', $currentScopes->pluck('warehouse_id')->filter()->all());
    @endphp

    <form method="POST"
          action="{{ $user->exists ? route('team.users.update', $user) : route('team.users.store') }}"
          class="max-w-4xl space-y-4"
          x-data="{ level: @js($scopeLevel) }">
        @csrf
        @if ($user->exists)
            @method('PUT')
        @endif

        <div class="card card-pad space-y-4">
            <p class="section-title">Identitas</p>

            <div class="form-grid">
                <div>
                    <label class="label" for="name">Nama Lengkap</label>
                    <input id="name" name="name" class="input" value="{{ old('name', $user->name) }}" required>
                    @error('name') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="job_title">Jabatan</label>
                    <input id="job_title" name="job_title" class="input" value="{{ old('job_title', $user->job_title) }}">
                </div>

                <div>
                    <label class="label" for="email">Email</label>
                    <input id="email" name="email" type="email" class="input" value="{{ old('email', $user->email) }}" required>
                    @error('email') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="username">Username</label>
                    <input id="username" name="username" class="input" value="{{ old('username', $user->username) }}" required>
                    @error('username') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="phone">Telepon / WhatsApp</label>
                    <input id="phone" name="phone" class="input" value="{{ old('phone', $user->phone) }}">
                </div>

                <div>
                    <label class="label" for="password">
                        Password {{ $user->exists ? '(kosongkan jika tidak diubah)' : '' }}
                    </label>
                    <input id="password" name="password" type="password" class="input" autocomplete="new-password"
                           @required(! $user->exists)>
                    @error('password') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="default_branch_id">Cabang Default</label>
                    <select id="default_branch_id" name="default_branch_id" class="input">
                        <option value="">-</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}"
                                @selected((int) old('default_branch_id', $user->default_branch_id) === $branch->id)>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" class="rounded text-brand-500 focus:ring-brand-500"
                       @checked(old('is_active', $user->is_active ?? true))>
                User aktif dan boleh login
            </label>
        </div>

        <div class="card card-pad space-y-3">
            <p class="section-title">Role</p>
            @error('roles') <p class="field-error">{{ $message }}</p> @enderror

            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($roles as $role)
                    <label class="border-hairline hover:bg-panel-soft flex cursor-pointer items-start gap-2 rounded-lg border p-2.5 text-sm">
                        <input type="checkbox" name="roles[]" value="{{ $role }}"
                               class="mt-0.5 rounded text-brand-500 focus:ring-brand-500"
                               @checked(in_array($role, old('roles', $currentRoles), true))>
                        <span>
                            {{ $role }}
                            <span class="text-muted block text-xs">
                                {{ \App\Modules\Auth\Support\RoleTemplate::all()[$role]['description'] ?? '' }}
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="card card-pad space-y-3">
            <p class="section-title">Scope Lokasi</p>
            <p class="text-muted text-sm">
                User tidak akan melihat data cabang atau gudang lain jika tidak diberikan aksesnya.
            </p>

            <div class="space-y-2">
                @foreach (\App\Modules\Core\Enums\ScopeLevel::cases() as $level)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="scope_level" value="{{ $level->value }}" x-model="level"
                               class="text-brand-500 focus:ring-brand-500">
                        {{ $level->label() }}
                    </label>
                @endforeach
            </div>

            <div x-show="level === 'branch'" x-cloak class="border-hairline space-y-2 border-t pt-3">
                <p class="label">Pilih Cabang</p>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    @foreach ($branches as $branch)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="scope_branches[]" value="{{ $branch->id }}"
                                   class="rounded text-brand-500 focus:ring-brand-500"
                                   @checked(in_array($branch->id, array_map('intval', (array) $selectedBranches), true))>
                            {{ $branch->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div x-show="level === 'warehouse'" x-cloak class="border-hairline space-y-2 border-t pt-3">
                <p class="label">Pilih Gudang</p>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ($warehouses as $warehouse)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="scope_warehouses[]" value="{{ $warehouse->id }}"
                                   class="rounded text-brand-500 focus:ring-brand-500"
                                   @checked(in_array($warehouse->id, array_map('intval', (array) $selectedWarehouses), true))>
                            {{ $warehouse->name }}
                            <span class="text-muted text-xs">({{ $warehouse->branch?->name }})</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button type="submit" class="btn-primary">Simpan</button>
            <a href="{{ route('team.users.index') }}" wire:navigate class="btn-secondary">Batal</a>
        </div>
    </form>
</x-layouts.app>
