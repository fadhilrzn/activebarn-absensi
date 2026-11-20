@props(['label', 'value', 'unit' => '', 'hint' => '', 'icon' => 'ticket', 'tone' => 'neutral'])
@php
    $iconClasses = match ($tone) {
        'success' => 'bg-brand-soft text-brand-ink',
        'warning' => 'bg-amber-100 text-amber-700',
        'dark' => 'bg-ink text-white',
        default => 'bg-slate-100 text-slate-600',
    };
@endphp
<div class="rounded-2xl border border-line bg-white p-5">
    <div class="flex items-start justify-between gap-3">
        <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $iconClasses }}">
            <x-icon :name="$icon" />
        </span>
    </div>
    <p class="mt-3 text-4xl font-extrabold tracking-tight">
        {{ $value }}<span class="ml-1.5 text-base font-medium text-slate-500">{{ $unit }}</span>
    </p>
    @if ($hint !== '')
        <p class="mt-2 text-xs text-slate-500">{{ $hint }}</p>
    @endif
</div>
