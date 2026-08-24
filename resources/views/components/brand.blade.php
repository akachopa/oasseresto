@props(['size' => 'md', 'withText' => true])

@php
    $box = match ($size) {
        'sm' => 'h-8 w-8 text-sm',
        'lg' => 'h-14 w-14 text-2xl',
        default => 'h-10 w-10 text-lg',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <span class="{{ $box }} inline-flex shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-400 to-brand-600 font-bold text-white shadow-sm">
        O
    </span>

    @if ($withText)
        <span class="leading-tight">
            <span class="block text-base font-bold tracking-tight">{{ config('oasse.brand.name') }}</span>
            <span class="block text-[10px] tracking-wide text-muted uppercase">Wholesale ERP</span>
        </span>
    @endif
</span>
