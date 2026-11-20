<x-layouts.app title="Scan Check-In">
    <div class="grid gap-6 lg:grid-cols-2">

        {{-- Pemindai --}}
        <section id="scanner" data-url="{{ route('scan.scan') }}" class="space-y-4">
            <div class="rounded-2xl border border-line bg-white p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-extrabold">Scan QR Code</h2>
                    <p id="scan-status" class="text-sm text-slate-500" aria-live="polite">Kamera belum aktif.</p>
                </div>

                <div class="relative mt-4 aspect-square w-full overflow-hidden rounded-xl bg-slate-900 sm:aspect-video lg:aspect-square">
                    <div id="reader" class="h-full w-full"></div>
                    <div id="reader-placeholder" class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-slate-300">
                        Tekan “Mulai Scan”, lalu izinkan akses kamera.
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <button id="start-btn" type="button" class="min-h-14 rounded-xl bg-brand px-4 text-base font-bold text-ink hover:brightness-95 disabled:opacity-40">Mulai Scan</button>
                    <button id="stop-btn" type="button" disabled class="min-h-14 rounded-xl border border-line px-4 text-base font-bold hover:bg-slate-50 disabled:opacity-40">Berhenti</button>
                </div>
            </div>

            @include('scan._result', ['result' => $result])
        </section>

        {{-- Pencarian manual --}}
        <section class="rounded-2xl border border-line bg-white p-5">
            <h2 class="text-lg font-extrabold">Cari booking manual</h2>
            <p class="text-sm text-slate-500">Dipakai jika QR tidak terbaca atau member tidak membawa QR. Hanya booking hari ini.</p>

            <form method="GET" action="{{ route('scan.index') }}" class="mt-4 flex gap-2">
                <label for="q" class="sr-only">Cari</label>
                <input id="q" name="q" type="search" value="{{ $q }}" placeholder="Nama, no. WhatsApp, atau kode booking"
                       class="min-h-12 w-full rounded-xl border border-line px-4 text-sm outline-none focus:border-ink">
                <button type="submit" class="min-h-12 rounded-xl bg-ink px-5 text-sm font-semibold text-white hover:bg-black">Cari</button>
            </form>

            <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">
                {{ $q === '' ? 'Sesi yang check-in-nya sedang dibuka' : 'Hasil pencarian hari ini' }}
            </p>

            <ul class="mt-2 divide-y divide-line">
                @forelse ($bookings as $booking)
                    @php
                        $session = $booking->classSession;
                        $att = $booking->attendance;
                        $hadir = $att?->status === \App\Enums\AttendanceStatus::Hadir;
                    @endphp
                    <li class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="truncate font-bold">{{ $booking->member->name }}</p>
                            <p class="truncate text-xs text-slate-500">
                                {{ $session->classType->name }} · {{ $session->starts_at->format('H:i') }}–{{ $session->ends_at->format('H:i') }} · {{ $session->room->name }}
                            </p>
                            <p class="font-mono text-xs text-slate-400">{{ $booking->booking_code }}</p>
                        </div>
                        @if ($hadir)
                            <x-badge color="success">Hadir {{ $att->checked_in_at->format('H:i') }}</x-badge>
                        @else
                            <form method="POST" action="{{ route('scan.manual', $booking) }}">
                                @csrf
                                <input type="hidden" name="q" value="{{ $q }}">
                                <button type="submit" class="min-h-11 whitespace-nowrap rounded-xl bg-brand px-4 text-sm font-bold text-ink hover:brightness-95">Check-In</button>
                            </form>
                        @endif
                    </li>
                @empty
                    <li class="py-8 text-center text-sm text-slate-500">
                        @if ($q === '')
                            Tidak ada kelas yang sedang dibuka untuk check-in. Ketik nama atau kode booking untuk mencari booking hari ini.
                        @else
                            Tidak ada booking aktif hari ini yang cocok dengan “{{ $q }}”.
                        @endif
                    </li>
                @endforelse
            </ul>
            <p class="mt-3 text-xs text-slate-400">Check-in dibuka {{ $openMinutes }} menit sebelum kelas dimulai dan ditutup saat kelas selesai.</p>
        </section>
    </div>
</x-layouts.app>
