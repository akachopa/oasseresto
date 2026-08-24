<x-layouts.app :title="$role->exists ? 'Sesuaikan Role' : 'Buat Role'">
    <x-page-header :title="$role->exists ? 'Sesuaikan Role: '.$role->name : 'Buat Role Baru'"
                   subtitle="Permission dikelompokkan per modul agar tidak menampilkan ratusan pilihan datar."
                   :back="route('team.roles.index')" />

    <form method="POST"
          action="{{ $role->exists ? route('team.roles.update', $role) : route('team.roles.store') }}"
          class="space-y-4">
        @csrf
        @if ($role->exists)
            @method('PUT')
        @endif

        @unless ($role->exists)
            <div class="card card-pad form-grid">
                <div>
                    <label class="label" for="name">Nama Role</label>
                    <input id="name" name="name" class="input" value="{{ old('name') }}" required>
                    @error('name') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="template">Mulai dari Template</label>
                    <select id="template" name="template" class="input">
                        <option value="">Tanpa template</option>
                        @foreach (\App\Modules\Auth\Support\RoleTemplate::all() as $name => $definition)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <p class="text-muted mt-1 text-xs">
                        Template dipakai bila tidak ada permission yang dicentang manual.
                    </p>
                </div>
            </div>
        @endunless

        <div class="space-y-3" x-data="{
            toggleModule(module, checked) {
                this.$root.querySelectorAll(`[data-module='${module}']`).forEach((el) => (el.checked = checked));
            }
        }">
            @foreach ($modules as $module => $definition)
                @php
                    $moduleGranted = collect(array_keys($definition['actions']))
                        ->filter(fn ($action) => in_array($module.'.'.$action, old('permissions', $granted), true))
                        ->count();
                @endphp

                <div class="card overflow-hidden" x-data="{ open: @js($moduleGranted > 0) }">
                    <div class="border-hairline flex items-center justify-between gap-2 border-b px-4 py-2.5">
                        <button type="button" @click="open = !open" class="flex items-center gap-2 text-left">
                            <x-icon name="chevron-down" class="h-4 w-4 transition" x-bind:class="open && 'rotate-180'" />
                            <span class="font-medium">{{ $definition['label'] }}</span>
                            <span class="text-muted text-xs">{{ $moduleGranted }}/{{ count($definition['actions']) }}</span>
                        </button>

                        <div class="flex items-center gap-1">
                            <button type="button" class="btn-ghost px-2 py-1 text-xs"
                                    @click="toggleModule(@js($module), true)">Pilih semua</button>
                            <button type="button" class="btn-ghost px-2 py-1 text-xs"
                                    @click="toggleModule(@js($module), false)">Kosongkan</button>
                        </div>
                    </div>

                    <div x-show="open" x-cloak class="grid grid-cols-1 gap-2 p-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($definition['actions'] as $action => $label)
                            @php $permission = $module.'.'.$action; @endphp
                            <label class="flex items-start gap-2 text-sm">
                                <input type="checkbox" name="permissions[]" value="{{ $permission }}"
                                       data-module="{{ $module }}"
                                       class="mt-0.5 rounded text-brand-500 focus:ring-brand-500"
                                       @checked(in_array($permission, old('permissions', $granted), true))>
                                <span>
                                    {{ $label }}
                                    <code class="text-muted block text-[11px]">{{ $permission }}</code>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex items-center gap-2">
            <button type="submit" class="btn-primary">Simpan Permission</button>
            <a href="{{ route('team.roles.index') }}" wire:navigate class="btn-secondary">Batal</a>
        </div>
    </form>
</x-layouts.app>
