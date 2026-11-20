<x-layouts.app title="Dashboard">
    <div class="space-y-6">

        {{-- Sambutan dan pemilih tanggal --}}
        <section class="flex flex-col gap-4 rounded-2xl border border-line bg-white p-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-brand-ink">{{ $date->translatedFormat('l, d F Y') }}</p>
                <h2 class="mt-1 text-2xl font-extrabold">{{ $greeting }}, {{ auth()->user()->name }}!</h2>
                <p class="mt-1 text-sm text-slate-500">Ringkasan booking dan kehadiran member pada tanggal ini.</p>
            </div>
            <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-2">
                <label for="date" class="sr-only">Tanggal</label>
                <input id="date" type="date" name="date" value="{{ $date->toDateString() }}" onchange="this.form.submit()"
                       class="rounded-xl border border-line px-3 py-2 text-sm font-medium">
                @unless ($date->isToday())
                    <a href="{{ route('dashboard') }}" class="rounded-xl border border-line px-3 py-2 text-sm font-semibold hover:bg-slate-50">Hari ini</a>
                @endunless
            </form>
        </section>

        {{-- Kartu ringkasan --}}
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Total Booking" :value="$totalBooking" unit="Member" icon="ticket"
                         hint="Booking aktif (tidak termasuk yang dibatalkan)" />
            <x-stat-card label="Sudah Check-In" :value="$hadir" unit="Hadir" icon="check" tone="success"
                         :hint="$totalBooking > 0 ? round($hadir / $totalBooking * 100, 1) . '% dari total booking' : 'Belum ada booking'" />
            <x-stat-card label="Belum Hadir" :value="$belumHadir" unit="Menunggu" icon="clock" tone="warning"
                         hint="Booking aktif yang belum check-in" />
            <x-stat-card label="QR Terkirim" :value="$qrTerkirim" unit="Pesan" icon="message" tone="dark"
                         :hint="$qrBelum . ' booking belum dikirimi QR'" />
        </section>

        <div class="grid gap-6 xl:grid-cols-3">

            {{-- Jadwal kelas --}}
            <section class="rounded-2xl border border-line bg-white p-6 xl:col-span-2">
                <h3 class="text-lg font-extrabold">Jadwal Kelas</h3>
                <p class="text-sm text-slate-500">Status check-in tiap sesi pada tanggal terpilih.</p>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    @forelse ($sessions as $session)
                        @php
                            $now = now();
                            [$statusLabel, $statusColor] = match (true) {
                                $now->greaterThan($session->ends_at) => ['Selesai', 'neutral'],
                                $now->between($session->starts_at, $session->ends_at) => ['Berlangsung', 'success'],
                                $session->isCheckinOpen($now) => ['Check-in dibuka', 'warning'],
                                default => ['Mendatang', 'neutral'],
                            };
                        @endphp
                        <article class="rounded-2xl border border-line p-4">
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-mono text-sm font-semibold">{{ $session->starts_at->format('H:i') }} – {{ $session->ends_at->format('H:i') }}</p>
                                <x-badge :color="$statusColor">{{ $statusLabel }}</x-badge>
                            </div>
                            <p class="mt-3 text-lg font-extrabold">{{ $session->classType->name }}</p>
                            <p class="text-sm text-slate-500">{{ $session->trainer->name }} · {{ $session->room->name }}</p>
                            <div class="mt-4 flex items-center justify-between border-t border-line pt-3 text-sm">
                                <span class="font-mono text-slate-500">{{ $session->active_bookings_count }} booking</span>
                                <span class="font-semibold text-brand-ink">{{ $session->checked_in_count }} hadir</span>
                            </div>
                        </article>
                    @empty
                        <p class="col-span-full rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-500">
                            Belum ada sesi kelas pada tanggal ini.
                        </p>
                    @endforelse
                </div>
            </section>

            {{-- Aktivitas terakhir --}}
            <section class="rounded-2xl border border-line bg-white p-6">
                <h3 class="text-lg font-extrabold">Aktivitas Terakhir</h3>
                <p class="text-sm text-slate-500">Hasil scan check-in terbaru.</p>

                <ul class="mt-5 space-y-3">
                    @forelse ($activities as $log)
                        <li @class([
                            'rounded-xl border p-3',
                            'border-line' => $log->result->color() === 'success',
                            'border-red-200 bg-red-50' => $log->result->color() !== 'success',
                        ])>
                            <div class="flex items-center justify-between gap-2">
                                <p class="truncate text-sm font-bold">{{ $log->booking?->member?->name ?? 'QR tidak dikenali' }}</p>
                                <span class="font-mono text-xs text-slate-500">{{ $log->scanned_at->format('H:i') }}</span>
                            </div>
                            @if ($log->booking)
                                <p class="text-xs text-slate-500">{{ $log->booking->classSession->classType->name }}</p>
                            @endif
                            <div class="mt-2">
                                <x-badge :color="$log->result->color()">{{ $log->result->label() }}</x-badge>
                            </div>
                        </li>
                    @empty
                        <li class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-500">Belum ada aktivitas scan.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</x-layouts.app>
