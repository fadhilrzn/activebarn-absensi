@props(['href', 'icon', 'active' => false])
<a href="{{ $href }}"
   @class([
       'flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition',
       'bg-ink text-white' => $active,
       'text-slate-600 hover:bg-slate-100' => ! $active,
   ])
   @if ($active) aria-current="page" @endif>
    <x-icon :name="$icon" />
    <span>{{ $slot }}</span>
</a>
