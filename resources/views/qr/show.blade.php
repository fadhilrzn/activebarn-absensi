<x-layouts.app title="Kirim QR">
    @php
        $session = $booking->classSession;
        $imageUrl = route('qr.image', $booking) . '?v=' . $booking->updated_at->timestamp;
    @endphp
    <div class="mx-auto max-w-5xl space-y-6">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('qr.index', ['date' => $session->starts_at->toDateString()]) }}" class="text-sm font-semibold text-slate-500 hover:text-ink">&larr; Kembali ke daftar</a>
            <div class="flex items-center gap-3 text-sm">
                <span class="text-slate-500">{{ $remaining }} QR belum terkirim pada tanggal ini</span>
                @if ($next)
                    <a href="{{ route('qr.show', $next) }}" class="rounded-xl border border-line px-3 py-1.5 font-semibold hover:bg-slate-50">Lewati ke berikutnya</a>
                @endif
            </div>
        </div>

        @error('qr')
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700" role="alert">{{ $message }}</div>
        @enderror

        <section class="rounded-2xl border border-line bg-white p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-extrabold">{{ $booking->member->name }}</h2>
                    <p class="text-sm text-slate-500">+{{ $booking->member->phone }} · {{ $session->classType->name }} · {{ $session->schedule_label }}</p>
                </div>
                @if ($booking->qr_sent_at)
                    <x-badge color="success">Terkirim {{ $booking->qr_sent_at->format('H:i') }} oleh {{ $booking->qrSender?->name ?? '–' }}</x-badge>
                @else
                    <x-badge color="warning">Belum dikirim</x-badge>
                @endif
            </div>
            @if ($ended)
                <p class="mt-4 rounded-xl bg-slate-100 px-4 py-3 text-sm font-medium text-slate-600">Kelas ini sudah selesai, QR tidak dapat dikirim.</p>
            @endif
        </section>

        <div class="grid gap-6 md:grid-cols-2">

            {{-- QR --}}
            <section class="rounded-2xl border border-line bg-white p-6">
                <h3 class="text-lg font-extrabold">1. Siapkan QR</h3>
                <div class="mt-4 flex justify-center rounded-xl border border-line bg-white p-4">
                    <img src="{{ $imageUrl }}" alt="QR Code booking {{ $booking->booking_code }}" class="size-64">
                </div>
                <p class="mt-2 text-center font-mono text-sm text-slate-500">{{ $booking->booking_code }}</p>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <button id="dl-btn" type="button" class="min-h-12 rounded-xl bg-ink px-4 text-sm font-bold text-white hover:bg-black">Unduh QR</button>
                    <button id="copy-qr-btn" type="button" class="min-h-12 rounded-xl border border-line px-4 text-sm font-bold hover:bg-slate-50">Salin QR</button>
                </div>
                <p class="mt-2 text-xs text-slate-500">Di WhatsApp Web, “Salin QR” lalu tempel dengan Ctrl+V. Di HP atau tablet, pakai “Unduh QR”.</p>

                <form method="POST" action="{{ route('qr.regenerate', $booking) }}" class="mt-4 border-t border-line pt-4">
                    @csrf
                    <button type="submit" class="text-sm font-semibold text-red-600 hover:underline"
                            onclick="return confirm('Buat QR baru? QR lama tidak akan berlaku lagi dan harus dikirim ulang ke member.')">
                        Buat ulang QR (QR lama dinonaktifkan)
                    </button>
                </form>
            </section>

            {{-- Pesan --}}
            <section class="rounded-2xl border border-line bg-white p-6">
                <h3 class="text-lg font-extrabold">2. Periksa pesan</h3>
                <label for="message" class="sr-only">Pesan WhatsApp</label>
                <textarea id="message" readonly rows="15" class="mt-4 w-full rounded-xl border border-line bg-slate-50 p-3 text-sm leading-relaxed">{{ $message }}</textarea>
                <button id="copy-msg-btn" type="button" class="mt-2 text-sm font-semibold underline">Salin pesan</button>

                @unless ($ended)
                    <h3 class="mt-6 text-lg font-extrabold">3. Kirim lewat WhatsApp</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        WhatsApp dibuka dengan pesan terisi. Lampirkan QR, lalu tekan kirim di WhatsApp. Sistem tidak mengirim apa pun otomatis.
                    </p>
                    <a href="{{ $waLink }}" target="_blank" rel="noopener noreferrer"
                       onclick="return confirm('WhatsApp akan dibuka dengan pesan terisi. Lampirkan QR lalu kirim secara manual. Lanjutkan?')"
                       class="mt-3 flex min-h-12 items-center justify-center rounded-xl bg-brand px-4 text-sm font-bold text-ink hover:brightness-95">
                        Buka WhatsApp
                    </a>

                    <form method="POST" action="{{ route('qr.sent', $booking) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="min-h-12 w-full rounded-xl border-2 border-ink px-4 text-sm font-bold hover:bg-slate-50">
                            {{ $booking->qr_sent_at ? 'Tandai terkirim ulang' : 'Sudah saya kirim' }}{{ $next ? ' & lanjut' : '' }}
                        </button>
                    </form>
                @endunless
            </section>
        </div>

        <p id="action-status" class="min-h-5 text-sm font-medium" role="status" aria-live="polite"></p>
    </div>

    <script>
        (function () {
            var svgUrl = @json($imageUrl);
            var code = @json($booking->booking_code);
            var statusEl = document.getElementById('action-status');

            function say(text, isError) {
                statusEl.textContent = text;
                statusEl.className = 'min-h-5 text-sm font-medium ' + (isError ? 'text-red-600' : 'text-brand-ink');
            }

            // SVG dari server -> PNG (browser yang mengubah; tanpa ekstensi PHP tambahan)
            async function toPng() {
                var response = await fetch(svgUrl, { credentials: 'same-origin' });
                if (!response.ok) throw new Error('QR gagal dimuat');
                var url = URL.createObjectURL(new Blob([await response.text()], { type: 'image/svg+xml' }));
                try {
                    var img = await new Promise(function (resolve, reject) {
                        var image = new Image();
                        image.onload = function () { resolve(image); };
                        image.onerror = reject;
                        image.src = url;
                    });
                    var size = 640;
                    var canvas = document.createElement('canvas');
                    canvas.width = size;
                    canvas.height = size;
                    var ctx = canvas.getContext('2d');
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, size, size);
                    ctx.imageSmoothingEnabled = false;
                    ctx.drawImage(img, 0, 0, size, size);
                    return await new Promise(function (resolve, reject) {
                        canvas.toBlob(function (blob) { blob ? resolve(blob) : reject(new Error('PNG gagal dibuat')); }, 'image/png');
                    });
                } finally {
                    URL.revokeObjectURL(url);
                }
            }

            document.getElementById('dl-btn').addEventListener('click', async function () {
                try {
                    var blob = await toPng();
                    var link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = 'qr-' + code + '.png';
                    link.click();
                    setTimeout(function () { URL.revokeObjectURL(link.href); }, 1000);
                    say('QR diunduh sebagai qr-' + code + '.png. Lampirkan ke chat WhatsApp.');
                } catch (e) {
                    say('QR gagal diunduh. Coba muat ulang halaman.', true);
                }
            });

            document.getElementById('copy-qr-btn').addEventListener('click', async function () {
                if (!navigator.clipboard || !window.ClipboardItem) {
                    say('Browser ini tidak mendukung salin gambar. Gunakan “Unduh QR”.', true);
                    return;
                }
                try {
                    await navigator.clipboard.write([new ClipboardItem({ 'image/png': toPng() })]);
                    say('QR disalin. Tempel (Ctrl+V) di chat WhatsApp.');
                } catch (e) {
                    say('QR gagal disalin. Gunakan “Unduh QR”.', true);
                }
            });

            document.getElementById('copy-msg-btn').addEventListener('click', async function () {
                try {
                    await navigator.clipboard.writeText(document.getElementById('message').value);
                    say('Pesan disalin.');
                } catch (e) {
                    say('Pesan gagal disalin. Blok teks lalu salin manual.', true);
                }
            });
        })();
    </script>
</x-layouts.app>
