@props([
    'padding' => 'default',
])

@php
    $paddingClasses = [
        'none' => '',
        'sm' => 'p-2',
        'default' => 'p-4',
        'lg' => 'p-6',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border border-border bg-card text-card-foreground shadow-sm ' . ($paddingClasses[$padding] ?? $paddingClasses['default'])]) }}>
    {{ $slot }}
</div>
