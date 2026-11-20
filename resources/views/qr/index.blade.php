<x-layouts.app title="QR Check-In & WhatsApp">
    <div class="space-y-6">

        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <strong>WhatsApp dibuka untuk pengiriman manual.</strong> Sistem tidak mengirim pesan otomatis: staf melampirkan QR lalu menekan kirim sendiri.
        </div>

        @error('qr')
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700" role="alert">{{ $message }}</div>
        @enderror

        <section class="grid gap-4 sm:grid-cols-3">
            <x-stat-card label="Booking aktif" :value="$total" unit="Member" icon="ticket" :hint="\Illuminate\Support\Carbon::parse($day)->translatedFormat('l, d F Y')" />
            <x-stat-card label="QR terkirim" :value="$sent" unit="Pesan" icon="check" tone="success" hint="Sudah ditandai terkirim oleh staf" />
            <x-stat-card label="Belum dikirim" :value="max($total - $sent, 0)" unit="Pesan" icon="message" tone="warning" hint="Termasuk sesi yang sudah selesai" />
        </section>

        <form method="GET" action="{{ route('qr.index') }}" class="grid gap-3 rounded-2xl border border-line bg-white p-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label for="date" class="sr-only">Tanggal</label>
                <input id="date" name="date" type="date" value="{{ $day }}" class="w-full rounded-xl border border-line px-3 py-2.5 text-sm">
            </div>
            <div>
                <label for="class_type" class="sr-only">Kelas</label>
                <select id="class_type" name="class_type" class="w-full rounded-xl border border-line px-3 py-2.5 text-sm">
                    <option value="">Semua kelas</option>
                    @foreach ($classTypes as $type)
                        <option value="{{ $type->id }}" @selected(request('class_type') == $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="qr" class="sr-only">Status QR</label>
                <select id="qr" name="qr" class="w-full rounded-xl border border-line px-3 py-2.5 text-sm">
                    <option value="">Semua status</option>
                    <option value="belum" @selected(request('qr') === 'belum')>Belum dikirim</option>
                    <option value="terkirim" @selected(request('qr') === 'terkirim')>Terkirim</option>
                </select>
            </div>
            <div class="flex gap-2 lg:col-span-2 lg:justify-end">
                <a href="{{ route('qr.index') }}" class="rounded-xl border border-line px-4 py-2.5 text-sm font-semibold hover:bg-slate-50">Reset</a>
                <button type="submit" class="rounded-xl bg-ink px-5 py-2.5 text-sm font-semibold text-white hover:bg-black">Terapkan</button>
                @if ($firstPending)
                    <a href="{{ route('qr.show', $firstPending) }}" class="rounded-xl bg-brand px-5 py-2.5 text-sm font-bold text-ink hover:brightness-95">Mulai antrean</a>
                @endif
            </div>
        </form>
        @error('date') <p class="text-sm font-medium text-red-600">{{ $message }}</p> @enderror

        <div class="overflow-x-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[52rem] text-left text-sm">
                <thead class="border-b border-line text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Member</th>
                        <th class="px-5 py-3 font-semibold">Kelas &amp; Trainer</th>
                        <th class="px-5 py-3 font-semibold">Jadwal</th>
                        <th class="px-5 py-3 font-semibold">Kode</th>
                        <th class="px-5 py-3 font-semibold">Status QR</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($bookings as $booking)
                        @php
                            $session = $booking->classSession;
                            $ended = $session->ends_at->isPast();
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-4">
                                <p class="font-bold">{{ $booking->member->name }}</p>
                                <p class="text-xs text-slate-500">+{{ $booking->member->phone }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="font-semibold">{{ $session->classType->name }}</p>
                                <p class="text-xs text-slate-500">{{ $session->trainer->name }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="font-semibold">{{ $session->starts_at->format('H:i') }}–{{ $session->ends_at->format('H:i') }}</p>
                                <p class="text-xs text-slate-500">{{ $session->room->name }}</p>
                            </td>
                            <td class="px-5 py-4 font-mono text-xs">{{ $booking->booking_code }}</td>
                            <td class="px-5 py-4">
                                @if ($booking->qr_sent_at)
                                    <x-badge color="success">Terkirim {{ $booking->qr_sent_at->format('H:i') }}</x-badge>
                                    <p class="mt-1 text-xs text-slate-500">oleh {{ $booking->qrSender?->name ?? '–' }}</p>
                                @else
                                    <x-badge color="warning">Belum dikirim</x-badge>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                @if ($ended)
                                    <span class="text-xs text-slate-400">Kelas selesai</span>
                                @else
                                    <a href="{{ route('qr.show', $booking) }}"
                                       class="rounded-lg bg-ink px-3 py-1.5 text-xs font-semibold text-white hover:bg-black">
                                        {{ $booking->qr_sent_at ? 'Kirim ulang' : 'Kirim QR' }}
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">
                                Tidak ada booking aktif yang cocok pada tanggal ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $bookings->links() }}
    </div>
</x-layouts.app>
