import { Html5Qrcode } from 'html5-qrcode';

/**
 * Pemindai QR di browser. Hasil scan dikirim ke server (POST JSON);
 * server yang memutuskan berhasil atau tidaknya check-in.
 */
export function initScanner() {
    const root = document.getElementById('scanner');
    if (!root) return;

    const url = root.dataset.url;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const startBtn = document.getElementById('start-btn');
    const stopBtn = document.getElementById('stop-btn');
    const statusEl = document.getElementById('scan-status');
    const placeholder = document.getElementById('reader-placeholder');

    let qr = null;
    let running = false;
    let busy = false;
    let lastToken = '';
    let lastAt = 0;

    const setStatus = (text, isError = false) => {
        statusEl.textContent = text;
        statusEl.classList.toggle('text-red-600', isError);
        statusEl.classList.toggle('text-slate-500', !isError);
    };

    const syncButtons = () => {
        startBtn.disabled = running;
        stopBtn.disabled = !running;
        placeholder.classList.toggle('hidden', running);
    };

    const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value ?? '';
    };

    function showResult(data) {
        const panel = document.getElementById('result');
        const okClass = panel.dataset.okClass.split(' ');
        const errClass = panel.dataset.errClass.split(' ');

        panel.classList.remove('hidden', ...okClass, ...errClass);
        panel.classList.add(...(data.success ? okClass : errClass));

        setText('result-title', data.title);
        setText('result-message', data.message);

        const detail = document.getElementById('result-detail');
        detail.classList.toggle('hidden', !data.booking);
        if (data.booking) {
            setText('result-member', data.booking.member);
            setText('result-class', `${data.booking.class} · ${data.booking.trainer}`);
            setText('result-room', data.booking.room);
            setText('result-code', data.booking.code);
            setText('result-schedule', data.booking.schedule);
        }

        if (navigator.vibrate) navigator.vibrate(data.success ? 120 : [80, 60, 80]);
    }

    async function submit(token) {
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ token }),
            });

            if (response.status === 401 || response.status === 419) {
                throw new Error('Sesi login berakhir. Muat ulang halaman dan masuk kembali.');
            }
            if (!response.ok) {
                throw new Error('Server menolak permintaan. Coba lagi.');
            }

            showResult(await response.json());
        } catch (error) {
            showResult({
                success: false,
                title: 'Gagal Menghubungi Server',
                message: error.message || 'Periksa koneksi internet lalu scan ulang.',
                booking: null,
            });
        }
    }

    function onDecode(text) {
        if (busy) return;
        // Cegah QR yang sama terkirim berulang saat masih di depan kamera.
        if (text === lastToken && Date.now() - lastAt < 4000) return;

        busy = true;
        lastToken = text;
        lastAt = Date.now();
        setStatus('Memeriksa QR...');

        submit(text).finally(() => {
            setStatus('Arahkan kamera ke QR Code member.');
            setTimeout(() => { busy = false; }, 1200);
        });
    }

    async function start() {
        if (!window.isSecureContext) {
            setStatus('Kamera hanya bisa dipakai lewat HTTPS atau localhost.', true);
            return;
        }

        qr = qr ?? new Html5Qrcode('reader');

        try {
            await qr.start(
                { facingMode: 'environment' },
                {
                    fps: 10,
                    qrbox: (width, height) => {
                        const side = Math.floor(Math.min(width, height) * 0.7);
                        return { width: side, height: side };
                    },
                },
                onDecode,
                () => {},
            );
            running = true;
            syncButtons();
            setStatus('Arahkan kamera ke QR Code member.');
        } catch (error) {
            running = false;
            syncButtons();
            setStatus('Kamera tidak dapat dibuka. Periksa izin kamera di browser.', true);
        }
    }

    async function stop() {
        if (qr && running) {
            try { await qr.stop(); } catch (error) { /* abaikan */ }
        }
        running = false;
        syncButtons();
        setStatus('Pemindai berhenti.');
    }

    startBtn.addEventListener('click', start);
    stopBtn.addEventListener('click', stop);
    window.addEventListener('pagehide', stop);
    syncButtons();
}
