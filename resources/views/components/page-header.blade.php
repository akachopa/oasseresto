@props(['title', 'subtitle' => null, 'back' => null])

<div class="mb-5 flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" wire:navigate class="mb-1 inline-flex items-center gap-1 text-xs text-muted hover:text-brand-600">
                <x-icon name="chevron-right" class="h-3.5 w-3.5 rotate-180" />
                Kembali
            </a>
        @endif

        <h1 class="truncate text-lg font-semibold sm:text-xl">{{ $title }}</h1>

        @if ($subtitle)
            <p class="mt-0.5 text-sm text-muted">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
