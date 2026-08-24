@props(['items'])

{{-- Bottom navigation mobile, maksimal 5 item, tap target besar (PLAN 8) --}}
<nav class="bg-panel border-hairline fixed inset-x-0 bottom-0 z-40 border-t pb-[env(safe-area-inset-bottom)] lg:hidden">
    <div class="grid grid-cols-5">
        @foreach ($items as $item)
            @php
                $isMore = $item['route'] === 'more';
                $active = ! $isMore && request()->routeIs($item['route']);
            @endphp

            @if ($isMore)
                <a href="{{ route('more') }}" wire:navigate
                   class="text-muted flex min-h-14 flex-col items-center justify-center gap-1 text-[11px] font-medium">
                    <x-icon :name="$item['icon']" class="h-5 w-5" />
                    {{ $item['label'] }}
                </a>
            @else
                <a href="{{ route($item['route']) }}" wire:navigate
                   class="relative flex min-h-14 flex-col items-center justify-center gap-1 text-[11px] font-medium {{ $active ? 'text-brand-600 dark:text-brand-400' : 'text-muted' }}">
                    <x-icon :name="$item['icon']" class="h-5 w-5" />
                    {{ $item['label'] }}
                    @if ($active)
                        <span class="absolute bottom-0 h-0.5 w-8 rounded-full bg-brand-500"></span>
                    @endif
                </a>
            @endif
        @endforeach
    </div>
</nav>
