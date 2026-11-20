<x-layouts.app title="Detail Booking">
    @php
        $session = $booking->classSession;
        $att = $booking->attendance;
    @endphp
    <div class="mx-auto max-w-4xl space-y-6">
        <a href="{{ route('bookings.index') }}" class="text-sm font-semibold text-slate-500 hover:text-ink">&larr; Kembali ke Data Booking</a>

        <section class="rounded-2xl border border-line bg-white p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-mono text-sm text-slate-500">{{ $booking->booking_code }}</p>
                    <h2 class="mt-1 text-2xl font-extrabold">{{ $booking->member->name }}</h2>
                    <p class="text-sm text-slate-500">+{{ $booking->member->phone }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-badge :color="$booking->status->color()">{{ $booking->status->label() }}</x-badge>
                    @if (! $booking->isActive())
                        <x-badge>Tanpa kehadiran</x-badge>
                    @elseif ($att?->status === \App\Enums\AttendanceStatus::Hadir)
                        <x-badge color="success">Hadir {{ $att->checked_in_at->format('H:i') }} ({{ $att->method?->label() }})</x-badge>
                    @elseif ($att)
                        <x-badge color="danger">{{ $att->status->label() }}</x-badge>
                    @else
                        <x-badge>Belum hadir</x-badge>
                    @endif
                </div>
            </div>

            <dl class="mt-6 grid gap-x-8 gap-y-4 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">Kelas</dt><dd class="font-semibold">{{ $session->classType->name }}</dd></div>
                <div><dt class="text-slate-500">Trainer</dt><dd class="font-semibold">{{ $session->trainer->name }}</dd></div>
                <div><dt class="text-slate-500">Jadwal</dt><dd class="font-semibold">{{ $session->schedule_label }} – {{ $session->ends_at->format('H:i') }}</dd></div>
                <div><dt class="text-slate-500">Ruangan · Nomor sesi</dt><dd class="font-semibold">{{ $session->room->name }} · #{{ $session->session_number }}</dd></div>
                <div><dt class="text-slate-500">Sumber</dt><dd class="font-semibold">{{ $booking->source->label() }}</dd></div>
                <div><dt class="text-slate-500">Paket</dt><dd class="font-semibold">{{ $booking->package_type ?? '–' }}</dd></div>
                <div><dt class="text-slate-500">Harga</dt><dd class="font-semibold">Rp {{ number_format($booking->price, 0, ',', '.') }}</dd></div>
                <div><dt class="text-slate-500">Metode pembayaran</dt><dd class="font-semibold">{{ $booking->payment_method ?? '–' }}</dd></div>
                <div><dt class="text-slate-500">QR terkirim</dt><dd class="font-semibold">{{ $booking->qr_sent_at ? $booking->qr_sent_at->translatedFormat('d M Y H:i') : 'Belum' }}</dd></div>
                <div><dt class="text-slate-500">Dicatat oleh</dt><dd class="font-semibold">{{ $booking->creator->name }}</dd></div>
                @if ($booking->cancel_notified_at)
                    <div><dt class="text-slate-500">Member memberi kabar</dt><dd class="font-semibold">{{ $booking->cancel_notified_at->translatedFormat('d M Y H:i') }}</dd></div>
                    <div><dt class="text-slate-500">Catatan pembatalan</dt><dd class="font-semibold">{{ $booking->cancel_note ?? '–' }}</dd></div>
                @endif
            </dl>
        </section>

        @if ($canCancel)
            <form method="POST" action="{{ route('bookings.cancel', $booking) }}" class="rounded-2xl border border-line bg-white p-6">
                @csrf
                <h3 class="text-lg font-extrabold">Batalkan booking</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Isi waktu member memberi kabar. Jika kurang dari {{ \App\Models\Setting::int('cancel_limit_hours', 3) }} jam sebelum kelas,
                    booking dicatat sebagai batal terlambat.
                </p>
                @error('cancel')
                    <p class="mt-3 text-sm font-medium text-red-600">{{ $message }}</p>
                @enderror
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-input label="Waktu member memberi kabar" name="notified_at" type="datetime-local" :value="$defaultNotifiedAt" required />
                    <x-input label="Catatan" name="note" placeholder="Opsional" />
                </div>
                <button type="submit" class="mt-5 rounded-xl bg-red-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-700"
                        onclick="return confirm('Batalkan booking ini?')">Batalkan booking</button>
            </form>
        @endif
    </div>
</x-layouts.app>
