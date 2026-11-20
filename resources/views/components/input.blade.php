@props(['label', 'name', 'type' => 'text', 'value' => null, 'required' => false, 'list' => null, 'hint' => null, 'placeholder' => null])
<div>
    <label for="{{ $name }}" class="text-sm font-semibold">{{ $label }}@if ($required)<span class="text-red-500"> *</span>@endif</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}"
           @if ($required) required @endif
           @if ($list) list="{{ $list }}" @endif
           @if ($placeholder) placeholder="{{ $placeholder }}" @endif
           {{ $attributes->merge(['class' => 'mt-1.5 w-full rounded-xl border border-line bg-white px-4 py-2.5 text-sm outline-none focus:border-ink']) }}>
    @if ($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
    @enderror
</div>
