<x-layouts.app title="Pengaturan">
    <div class="mx-auto max-w-4xl space-y-6">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-500">Aturan di sini langsung dipakai oleh check-in, pembatalan, dan pesan WhatsApp.</p>
            <a href="{{ route('users.index') }}" class="rounded-xl border border-line bg-white px-4 py-2.5 text-sm font-semibold hover:bg-slate-50">Kelola pengguna</a>
        </div>

        {{-- Studio dan aturan --}}
        <form method="POST" action="{{ route('settings.general') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <section class="rounded-2xl border border-line bg-white p-6">
                <h2 class="text-lg font-extrabold">Informasi studio</h2>
                <p class="text-sm text-slate-500">Nama, alamat, dan telepon dapat dipakai di template pesan lewat {studio}, {alamat}, dan {telepon}.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input label="Nama studio" name="studio_name" :value="$v['studio_name']" required />
                    </div>
                    <x-input label="Alamat" name="studio_address" :value="$v['studio_address']" />
                    <x-input label="Telepon" name="studio_phone" :value="$v['studio_phone']" placeholder="+62 812-3456-7890" />
                </div>
            </section>

            <section class="rounded-2xl border border-line bg-white p-6">
                <h2 class="text-lg font-extrabold">Aturan check-in dan pembatalan</h2>
                <p class="text-sm text-slate-500">Berlaku untuk seluruh sesi. QR Code hanya sah di dalam jendela check-in.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-3">
                    <x-input label="Jendela check-in (menit)" name="checkin_open_minutes" type="number" min="0" max="240" :value="$v['checkin_open_minutes']" required
                             hint="QR sah mulai sekian menit sebelum kelas sampai kelas selesai." />
                    <x-input label="Imbauan tiba lebih awal (menit)" name="arrive_early_minutes" type="number" min="0" max="120" :value="$v['arrive_early_minutes']" required
                             hint="Tertulis di pesan. Tidak boleh melebihi jendela check-in." />
                    <x-input label="Batas pembatalan (jam)" name="cancel_limit_hours" type="number" min="0" max="72" :value="$v['cancel_limit_hours']" required
                             hint="Kabar yang lebih lambat dicatat batal terlambat." />
                </div>
                <p class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-600">
                    Perubahan jendela check-in langsung berlaku untuk QR yang sudah dikirim. Perubahan batas pembatalan hanya berlaku untuk pembatalan berikutnya;
                    booking yang sudah dibatalkan tidak dihitung ulang.
                </p>
            </section>

            <div class="flex justify-end">
                <button type="submit" class="rounded-xl bg-ink px-6 py-2.5 text-sm font-semibold text-white hover:bg-black">Simpan pengaturan</button>
            </div>
        </form>

        {{-- Template pesan --}}
        <section class="rounded-2xl border border-line bg-white p-6">
            <h2 class="text-lg font-extrabold">Template pesan WhatsApp</h2>
            <p class="text-sm text-slate-500">Teks ini terisi otomatis saat staf membuka WhatsApp. Staf tetap mengirim pesan secara manual.</p>

            <form method="POST" action="{{ route('settings.template') }}" class="mt-5">
                @csrf
                @method('PUT')
                <label for="template" class="text-sm font-semibold">Teks template</label>
                <textarea id="template" name="template" rows="16" required
                          class="mt-1.5 w-full rounded-xl border border-line p-3 font-mono text-sm leading-relaxed outline-none focus:border-ink">{{ old('template', $template) }}</textarea>
                @error('template')
                    <p class="mt-1.5 text-sm font-medium text-red-600" role="alert">{{ $message }}</p>
                @enderror

                <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Sisipkan placeholder</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($placeholders as $token => $description)
                        <button type="button" data-insert="{{ $token }}" title="{{ $description }}"
                                class="rounded-lg border border-line px-2.5 py-1 font-mono text-xs hover:bg-slate-50">{{ $token }}</button>
                    @endforeach
                </div>

                <div class="mt-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pratinjau (data contoh)</p>
                    <p id="preview-warning" class="mt-1 min-h-4 text-xs font-medium text-red-600" aria-live="polite"></p>
                    <pre id="preview" class="mt-1 max-h-96 overflow-auto whitespace-pre-wrap rounded-xl bg-slate-50 p-4 text-sm leading-relaxed">{{ $preview }}</pre>
                </div>

                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <button type="submit" form="reset-template-form"
                            onclick="return confirm('Kembalikan template ke teks bawaan? Teks Anda saat ini akan diganti.')"
                            class="rounded-xl border border-line px-4 py-2.5 text-sm font-semibold hover:bg-slate-50">Kembalikan ke bawaan</button>
                    <button type="submit" class="rounded-xl bg-ink px-6 py-2.5 text-sm font-semibold text-white hover:bg-black">Simpan template</button>
                </div>
            </form>
            <form id="reset-template-form" method="POST" action="{{ route('settings.template.reset') }}">@csrf</form>
        </section>
    </div>

    <script>
        (function () {
            var area = document.getElementById('template');
            var preview = document.getElementById('preview');
            var warning = document.getElementById('preview-warning');
            var url = @json(route('settings.template.preview'));
            var csrf = document.querySelector('meta[name="csrf-token"]').content;
            var timer = null;

            async function refresh() {
                if (area.value.trim() === '') { preview.textContent = ''; warning.textContent = ''; return; }
                try {
                    var response = await fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ template: area.value })
                    });
                    if (!response.ok) return;
                    var data = await response.json();
                    preview.textContent = data.text;
                    warning.textContent = data.unknown.length ? 'Placeholder tidak dikenal: ' + data.unknown.join(', ') : '';
                } catch (e) { /* pratinjau gagal, abaikan */ }
            }

            area.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(refresh, 400); });
            document.querySelectorAll('[data-insert]').forEach(function (button) {
                button.addEventListener('click', function () {
                    area.setRangeText(button.dataset.insert, area.selectionStart, area.selectionEnd, 'end');
                    area.focus();
                    area.dispatchEvent(new Event('input'));
                });
            });
        })();
    </script>
</x-layouts.app>
