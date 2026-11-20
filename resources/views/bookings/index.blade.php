<x-layouts.app title="Data Booking">
    <div class="space-y-6">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-slate-500">Booking member per sesi kelas. Tambahkan satu per satu atau impor dari file Excel.</p>
            <div class="flex gap-2">
                <a href="{{ route('imports.create') }}" class="rounded-xl border border-line bg-white px-4 py-2.5 text-sm font-semibold hover:bg-slate-50">Import Excel</a>
                <a href="{{ route('bookings.create') }}" class="rounded-xl bg-ink px-4 py-2.5 text-sm font-semibold text-white hover:bg-black">Tambah Booking</a>
            </div>
        </section>

        <form method="GET" action="{{ route('bookings.index') }}" class="grid gap-3 rounded-2xl border border-line bg-white p-4 sm:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <label for="q" class="sr-only">Cari</label>
                <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="Cari nama, no. WhatsApp, atau kode booking"
                       class="w-full rounded-xl border border-line px-4 py-2.5 text-sm outline-none focus:border-ink">
            </div>
            <div>
                <label for="date" class="sr-only">Tanggal</label>
                <input id="date" name="date" type="date" value="{{ request('date') }}" class="w-full rounded-xl border border-line px-3 py-2.5 text-sm">
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
                <label for="status" class="sr-only">Status booking</label>
                <select id="status" name="status" class="w-full rounded-xl border border-line px-3 py-2.5 text-sm">
                    <option value="">Semua status</option>
                    @foreach (\App\Enums\BookingStatus::cases() as $case)
                        <option value="{{ $case->value }}" @selected(request('status') === $case->value)>{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="attendance" class="sr-only">Kehadiran</label>
                <select id="attendance" name="attendance" class="w-full rounded-xl border border-line px-3 py-2.5 text-sm">
                    <option value="">Semua kehadiran</option>
                    <option value="hadir" @selected(request('attendance') === 'hadir')>Hadir</option>
                    <option value="tidak_hadir" @selected(request('attendance') === 'tidak_hadir')>Tidak hadir</option>
                    <option value="belum" @selected(request('attendance') === 'belum')>Belum hadir</option>
                </select>
            </div>
            <div class="flex gap-2 sm:col-span-2 lg:col-span-6 lg:justify-end">
                <a href="{{ route('bookings.index') }}" class="rounded-xl border border-line px-4 py-2.5 text-sm font-semibold hover:bg-slate-50">Reset</a>
                <button type="submit" class="rounded-xl bg-ink px-5 py-2.5 text-sm font-semibold text-white hover:bg-black">Terapkan</button>
            </div>
        </form>
        @error('date') <p class="text-sm font-medium text-red-600">{{ $message }}</p> @enderror

        <div class="overflow-x-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[56rem] text-left text-sm">
                <thead class="border-b border-line text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Member</th>
                        <th class="px-5 py-3 font-semibold">Kelas &amp; Trainer</th>
                        <th class="px-5 py-3 font-semibold">Jadwal</th>
                        <th class="px-5 py-3 font-semibold">Kode</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold">Kehadiran</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($bookings as $booking)
                        @php
                            $session = $booking->classSession;
                            $att = $booking->attendance;
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
                                <p class="font-semibold">{{ $session->starts_at->translatedFormat('d M Y') }}</p>
                                <p class="text-xs text-slate-500">{{ $session->starts_at->format('H:i') }}–{{ $session->ends_at->format('H:i') }} · {{ $session->room->name }}</p>
                            </td>
                            <td class="px-5 py-4 font-mono text-xs">{{ $booking->booking_code }}</td>
                            <td class="px-5 py-4"><x-badge :color="$booking->status->color()">{{ $booking->status->label() }}</x-badge></td>
                            <td class="px-5 py-4">
                                @if (! $booking->isActive())
                                    <span class="text-slate-400">–</span>
                                @elseif ($att?->status === \App\Enums\AttendanceStatus::Hadir)
                                    <x-badge color="success">Hadir {{ $att->checked_in_at->format('H:i') }}</x-badge>
                                @elseif ($att)
                                    <x-badge color="danger">{{ $att->status->label() }}</x-badge>
                                @else
                                    <x-badge>Belum hadir</x-badge>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ route('bookings.show', $booking) }}" class="rounded-lg border border-line px-3 py-1.5 text-xs font-semibold hover:bg-white">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-sm text-slate-500">
                                Belum ada booking yang cocok. Tambahkan booking atau impor dari Excel.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $bookings->links() }}
    </div>
</x-layouts.app>
