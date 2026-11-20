<x-layouts.app title="Pratinjau Impor">
    @php
        $isPreview = $batch->status === \App\Enums\ImportStatus::Preview;
        $statusMap = [
            'valid' => ['Valid', 'success'],
            'duplicate' => ['Duplikat', 'warning'],
            'invalid' => ['Tidak valid', 'danger'],
        ];
    @endphp
    <div class="space-y-6">
        <a href="{{ route('imports.create') }}" class="text-sm font-semibold text-slate-500 hover:text-ink">&larr; Kembali ke Import Excel</a>

        <section class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line bg-white p-6">
            <div>
                <h2 class="text-lg font-extrabold">{{ $batch->file_name }}</h2>
                <p class="text-sm text-slate-500">Status: {{ $batch->status->label() }} · {{ $batch->created_at->translatedFormat('d M Y H:i') }}</p>
            </div>
            @if ($isPreview && ! $error)
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('imports.destroy', $batch) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="rounded-xl border border-line px-4 py-2.5 text-sm font-semibold hover:bg-slate-50">Batalkan</button>
                    </form>
                    @if ($summary['valid'] > 0)
                        <form method="POST" action="{{ route('imports.confirm', $batch) }}">
                            @csrf
                            <button type="submit" class="rounded-xl bg-ink px-5 py-2.5 text-sm font-semibold text-white hover:bg-black">
                                Impor {{ $summary['valid'] }} baris valid
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </section>

        @if ($error)
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700" role="alert">{{ $error }}</div>
        @endif
        @error('file')
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700" role="alert">{{ $message }}</div>
        @enderror

        <section class="grid gap-4 sm:grid-cols-3">
            <x-stat-card label="Baris valid" :value="$summary['valid']" icon="check" tone="success" hint="Akan disimpan saat dikonfirmasi" />
            <x-stat-card label="Duplikat" :value="$summary['duplicate']" icon="ticket" tone="warning" hint="Sudah ada atau dobel di file; dilewati" />
            <x-stat-card label="Tidak valid" :value="$summary['invalid']" icon="alert" tone="dark" hint="Perlu diperbaiki di Excel; dilewati" />
        </section>

        @if ($isPreview && ! $error)
            <div class="overflow-x-auto rounded-2xl border border-line bg-white">
                <table class="w-full min-w-[56rem] text-left text-sm">
                    <thead class="border-b border-line text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Baris</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Member</th>
                            <th class="px-5 py-3 font-semibold">Sesi</th>
                            <th class="px-5 py-3 font-semibold">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($rows as $row)
                            @php
                                $d = $row['data'];
                                $rawName = $row['raw']['name'] ?? null;
                                [$label, $color] = $statusMap[$row['status']];
                            @endphp
                            <tr>
                                <td class="px-5 py-3 font-mono text-xs">{{ $row['row'] }}</td>
                                <td class="px-5 py-3"><x-badge :color="$color">{{ $label }}</x-badge></td>
                                <td class="px-5 py-3">
                                    <p class="font-semibold">{{ $d['name'] ?? (is_scalar($rawName) ? $rawName : '–') }}</p>
                                    @if ($d) <p class="text-xs text-slate-500">+{{ $d['phone'] }}</p> @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if ($d)
                                        <p class="font-semibold">#{{ $d['session_number'] }} · {{ $d['class'] }}</p>
                                        <p class="text-xs text-slate-500">{{ $d['date'] }} {{ $d['start'] }}–{{ $d['end'] }} · {{ $d['room'] }}</p>
                                    @else
                                        <span class="text-slate-400">–</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-xs text-slate-600">{{ $row['message'] ?? 'Siap diimpor' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($truncated)
                <p class="text-sm text-slate-500">Hanya 500 baris pertama yang ditampilkan. Semua baris tetap diproses saat dikonfirmasi.</p>
            @endif
        @elseif (! $isPreview && ! empty($batch->error_report))
            <section class="rounded-2xl border border-line bg-white p-6">
                <h3 class="text-base font-extrabold">Baris yang dilewati</h3>
                <ul class="mt-3 space-y-1 text-sm text-slate-600">
                    @foreach ($batch->error_report as $item)
                        <li>Baris {{ $item['row'] }}: {{ $item['message'] }}</li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.app>
