@props(['title' => 'Belum ada data', 'message' => null, 'icon' => 'boxes'])

<div class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center">
    <span class="bg-panel-soft inline-flex h-14 w-14 items-center justify-center rounded-2xl">
        <x-icon :name="$icon" class="h-6 w-6 text-brand-500" />
    </span>

    <div>
        <p class="font-medium">{{ $title }}</p>
        @if ($message)
            <p class="text-muted mt-1 max-w-sm text-sm">{{ $message }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="mt-1 flex items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
