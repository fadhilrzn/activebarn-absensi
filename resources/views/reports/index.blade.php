<x-layouts.app title="Laporan Absensi">
    @php
        $exportQuery = request()->except('page');
        $sortLink = fn (string $col) => request()->fullUrlWithQuery([
            'sort' => $col,
            'dir' => ($f['sort'] === $col && $f['dir'] === 'asc') ? 'desc' : 'asc',
            'page' => null,
        ]);
        $arrow = fn (string $col) => $f['sort'] === $col ? ($f['dir'] === 'asc' ? ' ▲' : ' ▼') : '';
        $rate = $summary['tingkat'];
        $periodLabel = \Illuminate\Support\Carbon::parse($f['from'])->translatedFormat('d M Y') . ' – ' . \Illuminate\Support\Carbon::parse($f['to'])->translatedFormat('d M Y');
    @endphp

    <div class="space-y-6">

        {{-- Filter dan ekspor --}}
        <form method="GET" action="{{ route('reports.index') }}" class="rounded-2xl border border-line bg-white p-4">
            <input type="hidden" name="sort" value="{{ $f['sort'] }}">
            <input type="hidden" name="dir" value="{{ $f['dir'] }}">
            <input type="hidden" name="group" value="{{ $f['group'] }}">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <div>
                    <label for="from" class="text-xs font-semibold text-slate-500">Dari tanggal</label>
                    <input id="from" name="from" type="date" value="{{ $f['from'] }}" class="mt-1 w-full rounded-xl border border-line px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label for="to" class="text-xs font-semibold text-slate-500">Sampai tanggal</label>
                    <input id="to" name="to" type="date" value="{{ $f['to'] }}" class="mt-1 w-full rounded-xl border border-line px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label for="class_type" class="text-xs font-semibold text-slate-500">Kelas</label>
                    <select id="class_type" name="class_type" class="mt-1 w-full rounded-xl border border-line px-3 py-2.5 text-sm">
                        <option value="">Semua kelas</option>
                        @foreach ($classTypes as $type)
                            <option value="{{ $type->id }}" @selected($f["class_type"] == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="trainer" class="text-xs font-semibold text-slate-500">Trainer</label>
                    <select id="trainer" name="trainer" class="mt-1 w-full rounded-xl border border-line px-3 py-2.5 text-sm">
                        <option value="">Semua trainer</option>
                        @foreach ($trainers as $trainer)
                            <option value="{{ $trainer->id }}" @selected($f["trainer"] == $trainer->id)>{{ $trainer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-2">
                    <label for="status" class="text-xs font-semibold text-slate-500">Status kehadiran</label>
                    <select id="status" name="status" class="mt-1 w-full rounded-xl border border-line px-3 py-2.5 text-sm">
                        <option value="">Semua status</option>
                        @foreach ($states as $key => [$label])
                            <option value="{{ $key }}" @selected($f['status'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            @if ($errors->any())
                <p class="mt-3 text-sm font-medium text-red-600" role="alert">{{ $errors->first() }}</p>
            @endif
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-500">Periode: <strong class="text-ink">{{ $periodLabel }}</strong></p>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('reports.index') }}" class="rounded-xl border border-line px-4 py-2.5 text-sm font-semibold hover:bg-slate-50">Reset</a>
                    <button type="submit" class="rounded-xl bg-ink px-5 py-2.5 text-sm font-semibold text-white hover:bg-black">Terapkan</button>
                    <a href="{{ route('reports.export', ['format' => 'xlsx'] + $exportQuery) }}" class="rounded-xl bg-brand px-4 py-2.5 text-sm font-bold text-ink hover:brightness-95">Ekspor Excel</a>
                    <a href="{{ route('reports.export', ['format' => 'csv'] + $exportQuery) }}" class="rounded-xl border border-line px-4 py-2.5 text-sm font-semibold hover:bg-slate-50">Ekspor CSV</a>
                </div>
            </div>
        </form>

        {{-- Ringkasan --}}
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Booking aktif" :value="$summary['aktif']" unit="Member" icon="ticket" hint="Tidak termasuk booking yang dibatalkan" />
            <x-stat-card label="Hadir" :value="$summary['hadir']" unit="Member" icon="check" tone="success" hint="Sudah check-in" />
            <x-stat-card label="Tidak hadir" :value="$summary['tidak_hadir']" unit="Member" icon="alert" tone="dark" hint="Sesi sudah selesai tanpa check-in" />
            <div class="rounded-2xl border border-line bg-white p-5">
                <p class="text-sm font-medium text-slate-500">Tingkat kehadiran</p>
                <p class="mt-3 text-4xl font-extrabold tracking-tight">{{ $rate === null ? '–' : number_format($rate, 1, ',', '.') . '%' }}</p>
                <div class="mt-3 h-2 rounded-full bg-slate-100" role="img" aria-label="Tingkat kehadiran {{ $rate ?? 0 }} persen">
                    <div class="h-2 rounded-full bg-brand" style="width: {{ min(max($rate ?? 0, 0), 100) }}%"></div>
                </div>
                <p class="mt-2 text-xs text-slate-500">Hadir ÷ (hadir + tidak hadir)</p>
            </div>
            <x-stat-card label="Belum" :value="$summary['belum']" unit="Member" icon="clock" tone="warning" hint="Sesi belum selesai" />
            <x-stat-card label="Batal" :value="$summary['batal']" unit="Booking" icon="booking" hint="Member memberi kabar tepat waktu" />
            <x-stat-card label="Batal terlambat" :value="$summary['batal_terlambat']" unit="Booking" icon="alert" tone="warning" hint="Kabar kurang dari batas jam sebelum kelas" />
        </section>

        {{-- Rekap per kelas, trainer, atau member --}}
        <section class="rounded-2xl border border-line bg-white p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-extrabold">Rekap per {{ ['kelas' => 'kelas', 'trainer' => 'trainer', 'member' => 'member'][$f['group']] }}</h2>
                <div class="flex gap-1 rounded-xl bg-slate-100 p-1 text-sm font-semibold">
                    @foreach (['kelas' => 'Kelas', 'trainer' => 'Trainer', 'member' => 'Member'] as $key => $label)
                        <a href="{{ request()->fullUrlWithQuery(['group' => $key, 'page' => null]) }}"
                           @class(['rounded-lg px-3 py-1.5', 'bg-white shadow-sm' => $f['group'] === $key, 'text-slate-600' => $f['group'] !== $key])>{{ $label }}</a>
                    @endforeach
                </div>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <thead class="border-b border-line text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="py-2 pr-4 font-semibold">{{ ucfirst($f['group']) }}</th>
                            <th class="py-2 pr-4 text-right font-semibold">Sesi</th>
                            <th class="py-2 pr-4 text-right font-semibold">Booking aktif</th>
                            <th class="py-2 pr-4 text-right font-semibold">Hadir</th>
                            <th class="py-2 pr-4 text-right font-semibold">Tidak hadir</th>
                            <th class="py-2 pr-4 text-right font-semibold">Batal</th>
                            <th class="py-2 text-right font-semibold">Tingkat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($groups as $g)
                            <tr>
                                <td class="py-2.5 pr-4 font-semibold">
                                    {{ $g['label'] }}
                                    @if ($g['phone']) <span class="block text-xs font-normal text-slate-500">+{{ $g['phone'] }}</span> @endif
                                </td>
                                <td class="py-2.5 pr-4 text-right">{{ $g['sesi'] }}</td>
                                <td class="py-2.5 pr-4 text-right">{{ $g['aktif'] }}</td>
                                <td class="py-2.5 pr-4 text-right">{{ $g['hadir'] }}</td>
                                <td class="py-2.5 pr-4 text-right">{{ $g['tidak_hadir'] }}</td>
                                <td class="py-2.5 pr-4 text-right">{{ $g['batal'] + $g['batal_terlambat'] }}</td>
                                <td class="py-2.5 text-right font-semibold">{{ $g['tingkat'] === null ? '–' : number_format($g['tingkat'], 1, ',', '.') . '%' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-8 text-center text-slate-500">Tidak ada data pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($groups->count() >= 100)
                <p class="mt-2 text-xs text-slate-500">Menampilkan 100 baris teratas. Persempit periode atau filter untuk melihat sisanya.</p>
            @endif
        </section>

        {{-- Tabel rincian --}}
        <section class="overflow-x-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[58rem] text-left text-sm">
                <thead class="border-b border-line text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold"><a href="{{ $sortLink('member') }}">Member{{ $arrow('member') }}</a></th>
                        <th class="px-5 py-3 font-semibold"><a href="{{ $sortLink('kelas') }}">Kelas{{ $arrow('kelas') }}</a></th>
                        <th class="px-5 py-3 font-semibold"><a href="{{ $sortLink('trainer') }}">Trainer{{ $arrow('trainer') }}</a></th>
                        <th class="px-5 py-3 font-semibold"><a href="{{ $sortLink('jadwal') }}">Jadwal{{ $arrow('jadwal') }}</a></th>
                        <th class="px-5 py-3 font-semibold"><a href="{{ $sortLink('checkin') }}">Check-in{{ $arrow('checkin') }}</a></th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($bookings as $booking)
                        @php
                            $session = $booking->classSession;
                            $att = $booking->attendance;
                            $key = \App\Services\AttendanceReport::stateKey($booking->status->value, $att?->status?->value, $att !== null, $session->ends_at, $now);
                            [$stateLabel, $stateColor] = $states[$key];
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3">
                                <p class="font-bold">{{ $booking->member->name }}</p>
                                <p class="text-xs text-slate-500">+{{ $booking->member->phone }}</p>
                            </td>
                            <td class="px-5 py-3 font-semibold">{{ $session->classType->name }}</td>
                            <td class="px-5 py-3">{{ $session->trainer->name }}</td>
                            <td class="px-5 py-3">
                                <p class="font-semibold">{{ $session->starts_at->translatedFormat('d M Y') }}</p>
                                <p class="text-xs text-slate-500">{{ $session->starts_at->format('H:i') }}–{{ $session->ends_at->format('H:i') }} · {{ $session->room->name }}</p>
                            </td>
                            <td class="px-5 py-3">
                                @if ($att?->checked_in_at)
                                    <p class="font-semibold">{{ $att->checked_in_at->format('d/m H:i') }}</p>
                                    <p class="text-xs text-slate-500">{{ $att->method?->label() }}</p>
                                @else
                                    <span class="text-slate-400">–</span>
                                @endif
                            </td>
                            <td class="px-5 py-3"><x-badge :color="$stateColor">{{ $stateLabel }}</x-badge></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">
                                Tidak ada data yang cocok dengan filter. Ubah periode atau hapus filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{ $bookings->links() }}

        <p class="text-xs text-slate-400">
            Status dihitung dari data: booking aktif tanpa check-in pada sesi yang sudah selesai terhitung tidak hadir.
            File CSV memakai pemisah titik koma agar terbuka benar di Excel berbahasa Indonesia.
        </p>
    </div>
</x-layouts.app>
