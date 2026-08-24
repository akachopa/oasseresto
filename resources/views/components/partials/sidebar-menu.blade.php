@props(['groups'])

<nav class="space-y-1 p-3">
    @foreach ($groups as $group)
        @php
            $isOpen = collect($group['items'])->contains(fn ($item) => request()->routeIs($item['route']));
            $single = count($group['items']) === 1;
        @endphp

        @if ($single)
            <a href="{{ route($group['items'][0]['route']) }}" wire:navigate
               class="nav-link {{ request()->routeIs($group['items'][0]['route']) ? 'nav-link-active' : '' }}">
                <x-icon :name="$group['icon']" class="h-5 w-5 shrink-0" />
                {{ $group['label'] }}
            </a>
        @else
            <div x-data="{ open: @js($isOpen) }">
                <button type="button" @click="open = !open" class="nav-link w-full justify-between">
                    <span class="flex items-center gap-3">
                        <x-icon :name="$group['icon']" class="h-5 w-5 shrink-0" />
                        {{ $group['label'] }}
                    </span>
                    <x-icon name="chevron-down" class="h-4 w-4 transition" x-bind:class="open && 'rotate-180'" />
                </button>

                <div x-show="open" x-cloak class="border-hairline mt-0.5 ml-4 space-y-0.5 border-l pl-3">
                    @foreach ($group['items'] as $item)
                        <a href="{{ route($item['route']) }}" wire:navigate
                           class="nav-link py-1.5 text-[13px] {{ request()->routeIs($item['route']) ? 'nav-link-active' : '' }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</nav>
