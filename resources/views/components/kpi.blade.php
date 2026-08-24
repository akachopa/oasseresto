@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'neutral',
    'href' => null,
    'icon' => null,
])

@php
    $toneClass = match ($tone) {
        'positive' => 'text-positive',
        'negative' => 'text-negative',
        'caution' => 'text-caution',
        'informative' => 'text-informative',
        default => '',
    };

    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif
    class="card card-pad group flex flex-col gap-1 {{ $href ? 'transition hover:border-brand-400 hover:shadow-md' : '' }}">
    <div class="flex items-center justify-between gap-2">
        <span class="text-xs font-medium tracking-wide text-muted uppercase">{{ $label }}</span>
        @if ($icon)
            <x-icon :name="$icon" class="h-4 w-4 text-muted" />
        @endif
    </div>

    <span class="kpi-value {{ $toneClass }}">{{ $value }}</span>

    @if ($hint)
        <span class="text-xs text-muted">{{ $hint }}</span>
    @endif
</{{ $tag }}>
