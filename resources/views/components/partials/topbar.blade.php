@php
    $user = auth()->user();
    $scope = app(\App\Modules\Core\Services\ScopeManager::class);
    $quickCreate = \App\Modules\Core\Support\Navigation::quickCreate($user);
    $branches = \App\Modules\Company\Models\Branch::active()->orderBy('name')->get()
        ->filter(fn ($branch) => $scope->canAccessBranch($branch->id));
    $activeBranch = $scope->branchId();
@endphp

<header class="bg-panel border-hairline sticky top-0 z-30 flex h-16 items-center gap-2 border-b px-4 sm:px-6">
    <button type="button" class="btn-icon text-muted lg:hidden" @click="mobileNav = true">
        <x-icon name="list" />
    </button>

    <a href="{{ route('dashboard') }}" wire:navigate class="lg:hidden">
        <x-brand size="sm" :with-text="false" />
    </a>

    {{-- Global search Cmd/Ctrl + K (PLAN 47) --}}
    <div class="ml-1 hidden flex-1 sm:block">
        <button type="button" @click="$dispatch('open-global-search')"
                class="border-hairline bg-canvas text-muted flex w-full max-w-md items-center gap-2 rounded-lg border px-3 py-2 text-sm transition hover:border-brand-400">
            <x-icon name="search" class="h-4 w-4" />
            <span>Cari produk, customer, invoice...</span>
            <kbd class="border-hairline ml-auto rounded border px-1.5 py-0.5 text-[10px]">Ctrl K</kbd>
        </button>
    </div>

    <div class="ml-auto flex items-center gap-1.5">
        @if ($branches->count() > 1)
            <form method="POST" action="{{ route('branch.switch') }}" class="hidden sm:block">
                @csrf
                <select name="branch_id" class="input w-auto py-1.5 text-xs" onchange="this.form.submit()">
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected($activeBranch === $branch->id)>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </form>
        @endif

        @if ($quickCreate !== [])
            <div x-data="{ open: false }" class="relative">
                <button type="button" @click="open = !open" class="btn-primary px-2.5 py-1.5">
                    <x-icon name="plus" class="h-4 w-4" />
                    <span class="hidden sm:inline">Buat</span>
                </button>

                <div x-show="open" x-cloak @click.outside="open = false"
                     class="card absolute right-0 mt-2 w-56 overflow-hidden py-1 shadow-lg">
                    @foreach ($quickCreate as $item)
                        <a href="{{ route($item['route']) }}" wire:navigate
                           class="hover:bg-panel-soft block px-3 py-2 text-sm">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if (Route::has('notifications.index'))
            <a href="{{ route('notifications.index') }}" wire:navigate class="btn-icon text-muted hover:bg-panel-soft">
                <x-icon name="bell" />
            </a>
        @endif

        <button type="button" onclick="window.oasseToggleTheme()" class="btn-icon text-muted hover:bg-panel-soft">
            <x-icon name="sun" class="h-5 w-5 dark:hidden" />
            <x-icon name="moon" class="hidden h-5 w-5 dark:block" />
        </button>

        <div x-data="{ open: false }" class="relative">
            <button type="button" @click="open = !open"
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-500/15 text-sm font-semibold text-brand-700 dark:text-brand-300">
                {{ $user?->initials }}
            </button>

            <div x-show="open" x-cloak @click.outside="open = false" class="card absolute right-0 mt-2 w-60 overflow-hidden shadow-lg">
                <div class="border-hairline border-b px-3 py-2.5">
                    <p class="truncate text-sm font-semibold">{{ $user?->name }}</p>
                    <p class="text-muted truncate text-xs">{{ $user?->roles->pluck('name')->join(', ') ?: $user?->email }}</p>
                    <p class="text-muted mt-1 truncate text-xs">{{ $user?->company?->name }}</p>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-negative hover:bg-negative/5 flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm">
                        <x-icon name="logout" class="h-4 w-4" />
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>

<livewire:core.global-search />
