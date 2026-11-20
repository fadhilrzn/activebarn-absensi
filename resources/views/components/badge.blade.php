@props(['color' => 'neutral'])
@php
    $classes = match ($color) {
        'success' => 'bg-brand-soft text-brand-ink',
        'warning' => 'bg-amber-100 text-amber-800',
        'danger' => 'bg-red-100 text-red-700',
        default => 'bg-slate-100 text-slate-600',
    };
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {$classes}"]) }}>{{ $slot }}</span>
