@php
    $ok = $result['success'] ?? null;
    $b = $result['booking'] ?? null;
@endphp
<div id="result" role="status" aria-live="polite"
     data-ok-class="border-brand bg-brand-soft" data-err-class="border-red-300 bg-red-50"
     @class([
         'rounded-2xl border-2 p-5',
         'hidden' => $result === null,
         'border-brand bg-brand-soft' => $ok === true,
         'border-red-300 bg-red-50' => $ok === false,
     ])>
    <p id="result-title" class="text-2xl font-extrabold">{{ $result['title'] ?? '' }}</p>
    <p id="result-message" class="mt-1 text-base font-medium">{{ $result['message'] ?? '' }}</p>

    <dl id="result-detail" @class(['mt-4 grid gap-3 border-t border-black/10 pt-4 text-sm sm:grid-cols-2', 'hidden' => ! $b])>
        <div><dt class="text-slate-500">Member</dt><dd id="result-member" class="text-base font-bold">{{ $b['member'] ?? '' }}</dd></div>
        <div><dt class="text-slate-500">Kelas · Trainer</dt><dd id="result-class" class="font-semibold">{{ $b ? $b['class'] . ' · ' . $b['trainer'] : '' }}</dd></div>
        <div><dt class="text-slate-500">Studio</dt><dd id="result-room" class="font-semibold">{{ $b['room'] ?? '' }}</dd></div>
        <div><dt class="text-slate-500">Kode booking</dt><dd id="result-code" class="font-mono font-semibold">{{ $b['code'] ?? '' }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-slate-500">Jadwal</dt><dd id="result-schedule" class="font-semibold">{{ $b['schedule'] ?? '' }}</dd></div>
    </dl>
</div>
