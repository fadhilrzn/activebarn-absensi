<x-layouts.app title="Import Excel">
    <div class="mx-auto max-w-3xl space-y-6">
        <a href="{{ route('bookings.index') }}" class="text-sm font-semibold text-slate-500 hover:text-ink">&larr; Kembali ke Data Booking</a>

        <section class="rounded-2xl border border-line bg-white p-6">
            <h2 class="text-lg font-extrabold">Unggah file booking</h2>
            <p class="mt-1 text-sm text-slate-500">
                File akan diperiksa lebih dulu. Tidak ada data yang tersimpan sebelum Anda mengonfirmasi pada halaman pratinjau.
            </p>

            <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="file" class="text-sm font-semibold">File Excel (.xlsx, maks. 5 MB)</label>
                    <input id="file" name="file" type="file" accept=".xlsx" required
                           class="mt-1.5 w-full rounded-xl border border-dashed border-line bg-slate-50 px-4 py-6 text-sm">
                    @error('file')
                        <p class="mt-1.5 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="rounded-xl bg-ink px-5 py-2.5 text-sm font-semibold text-white hover:bg-black">Periksa file</button>
                    <a href="{{ route('imports.template') }}" class="rounded-xl border border-line px-4 py-2.5 text-sm font-semibold hover:bg-slate-50">Unduh template</a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-line bg-white p-6 text-sm">
            <h3 class="text-base font-extrabold">Kolom yang dibaca</h3>
            <p class="mt-1 text-slate-500">Satu baris = satu booking. Baris pertama harus berisi judul kolom berikut.</p>
            <ul class="mt-3 grid gap-1 text-slate-600 sm:grid-cols-2">
                @foreach (\App\Services\BookingImportService::TEMPLATE_HEADERS as $header)
                    <li>• {{ $header }}</li>
                @endforeach
            </ul>
            <p class="mt-3 text-slate-500">
                Nomor Sesi harus unik per sesi. Sistem menolak baris yang memakai nomor sesi untuk dua sesi berbeda.
            </p>
        </section>

        @if ($recent->isNotEmpty())
            <section class="rounded-2xl border border-line bg-white p-6">
                <h3 class="text-base font-extrabold">Impor terakhir</h3>
                <ul class="mt-3 divide-y divide-line text-sm">
                    @foreach ($recent as $batch)
                        <li class="flex items-center justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <p class="truncate font-semibold">{{ $batch->file_name }}</p>
                                <p class="text-xs text-slate-500">{{ $batch->created_at->translatedFormat('d M Y H:i') }} · {{ $batch->importer->name }} · {{ $batch->valid_rows }} valid</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-badge :color="$batch->status === \App\Enums\ImportStatus::Confirmed ? 'success' : 'neutral'">{{ $batch->status->label() }}</x-badge>
                                <a href="{{ route('imports.show', $batch) }}" class="text-xs font-semibold underline">Lihat</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.app>
