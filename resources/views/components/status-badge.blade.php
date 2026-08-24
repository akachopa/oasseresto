@props(['status'])

@php
    $label = is_object($status) && method_exists($status, 'label') ? $status->label() : (string) $status;
    $color = is_object($status) && method_exists($status, 'color') ? $status->color() : 'muted';
@endphp

<span class="badge-{{ $color }}">{{ $label }}</span>
