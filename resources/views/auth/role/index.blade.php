<x-layouts.app title="Role & Permission">
    <x-page-header title="Role & Permission" subtitle="Mulai dari template, lalu sesuaikan per modul dan aksi.">
        <x-slot:actions>
            <a href="{{ route('team.roles.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" class="h-4 w-4" /> Buat Role
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($roles as $role)
            <div class="card card-pad flex flex-col gap-2">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ $role->name }}</p>
                        <p class="text-muted mt-0.5 text-xs">
                            {{ $templates[$role->name]['description'] ?? 'Role kustom' }}
                        </p>
                    </div>
                    @if (isset($templates[$role->name]))
                        <span class="badge-muted shrink-0">Template</span>
                    @endif
                </div>

                <div class="text-muted flex items-center gap-3 text-xs">
                    <span>{{ $role->permissions_count }} permission</span>
                    <span>{{ $role->users_count }} user</span>
                </div>

                <div class="border-hairline mt-1 flex items-center gap-2 border-t pt-2">
                    <a href="{{ route('team.roles.edit', $role) }}" wire:navigate class="btn-secondary px-2.5 py-1.5 text-xs">
                        <x-icon name="pencil" class="h-3.5 w-3.5" /> Sesuaikan
                    </a>

                    @if ($role->users_count === 0 && ! isset($templates[$role->name]))
                        <form method="POST" action="{{ route('team.roles.hapus', $role) }}"
                              onsubmit="return confirm('Hapus role ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-ghost text-negative px-2.5 py-1.5 text-xs">Hapus</button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.app>
