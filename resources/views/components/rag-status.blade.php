@props([
    'level' => 'green',
    'label' => null,
    'showDot' => true,
    'showLabel' => true,
    'size' => 'md',
])

@php
    $dotSizes = ['sm' => 'rag-dot-sm', 'md' => 'rag-dot', 'lg' => 'rag-dot-lg'];
    $dotClass = $dotSizes[$size] ?? 'rag-dot';
    $colorClass = match ($level) {
        'red' => 'rag-red',
        'orange' => 'rag-orange',
        'amber' => 'rag-amber',
        'yellow' => 'rag-yellow',
        'green' => 'rag-green',
        'blue' => 'rag-blue',
        default => 'rag-green',
    };
@endphp

<span class="rag-indicator d-inline-flex align-items-center gap-1">
    @if ($showDot)
        <span class="{{ $dotClass }} {{ $colorClass }}"></span>
    @endif
    @if ($showLabel && $label)
        <span class="rag-label {{ $colorClass }}-text">{{ $label }}</span>
    @endif
</span>
