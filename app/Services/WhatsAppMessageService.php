<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Setting;

/**
 * Menyusun pesan WhatsApp dari template di tabel settings (kunci whatsapp_template).
 * Template dapat diubah di halaman Pengaturan.
 *
 * WhatsApp tidak bisa menerima lampiran dari tautan wa.me, jadi QR dilampirkan
 * staf secara manual; sistem hanya mengisi teks pesannya.
 */
class WhatsAppMessageService
{
    public const DEFAULT_TEMPLATE = <<<'TXT'
*QR Check-In Kamu Sudah Siap!*

Halo, {nama}!

Tunjukkan QR Code ini kepada resepsionis saat tiba di Active Barn untuk check-in kelas.

Nama: {nama}
Kelas: {kelas}
Instruktur: {trainer}
Studio: Active Barn - {ruangan}
Jadwal: {jadwal}
Kode Booking: {kode}

Mohon tiba {tiba_awal} menit sebelum kelas dimulai. Jika ingin reschedule, informasikan kepada kami paling lambat {batas_batal} jam sebelum kelas dimulai.

Sampai jumpa di Active Barn!
#MoveBeyondToday
TXT;

    /** @var array<string, string> placeholder => keterangan (dipakai di halaman Pengaturan) */
    public const PLACEHOLDERS = [
        '{nama}' => 'Nama member',
        '{kelas}' => 'Nama kelas',
        '{trainer}' => 'Nama trainer',
        '{ruangan}' => 'Nama ruangan',
        '{jadwal}' => 'Hari, tanggal, dan jam kelas',
        '{kode}' => 'Kode booking',
        '{tiba_awal}' => 'Menit tiba lebih awal (dari pengaturan)',
        '{batas_batal}' => 'Batas jam pembatalan (dari pengaturan)',
        '{studio}' => 'Nama studio',
        '{alamat}' => 'Alamat studio',
        '{telepon}' => 'Telepon studio',
    ];

    public function template(): string
    {
        return Setting::get('whatsapp_template') ?: self::DEFAULT_TEMPLATE;
    }

    public function render(Booking $booking): string
    {
        $booking->loadMissing(['member', 'classSession.classType', 'classSession.trainer', 'classSession.room']);
        $session = $booking->classSession;

        return $this->fill($this->template(), [
            '{nama}' => $booking->member->name,
            '{kelas}' => $session->classType->name,
            '{trainer}' => $session->trainer->name,
            '{ruangan}' => $session->room->name,
            '{jadwal}' => $session->schedule_label,
            '{kode}' => $booking->booking_code,
        ] + $this->settingValues());
    }

    /** Pratinjau dengan data contoh, untuk halaman Pengaturan. */
    public function renderSample(string $template): string
    {
        $start = now()->addDay()->setTime(9, 30);

        return $this->fill($template, [
            '{nama}' => 'Siti Aminah',
            '{kelas}' => 'Hot Pilates',
            '{trainer}' => 'Amanda K.',
            '{ruangan}' => 'Move Room',
            '{jadwal}' => $start->translatedFormat('l, d F Y') . ' | 09:30',
            '{kode}' => 'AB-' . now()->format('ym') . '-K7M2Q',
        ] + $this->settingValues());
    }

    /** Tautan wa.me dengan teks terisi. Staf menekan kirim sendiri di WhatsApp. */
    public function link(Booking $booking): string
    {
        $booking->loadMissing('member');

        return $booking->member->whatsappLink($this->render($booking));
    }

    /**
     * Placeholder pada template yang tidak dikenal (mis. salah ketik "{nma}").
     *
     * @return list<string>
     */
    public function unknownPlaceholders(string $template): array
    {
        preg_match_all('/\{[A-Za-z_]+\}/', $template, $matches);

        return array_values(array_diff(array_unique($matches[0]), array_keys(self::PLACEHOLDERS)));
    }

    /** Pengisian satu kali jalan: isi nilai yang kebetulan berbentuk "{kode}" tidak diproses ulang. */
    public function fill(string $template, array $values): string
    {
        return strtr($template, $values);
    }

    /** @return array<string, string> */
    private function settingValues(): array
    {
        return [
            '{tiba_awal}' => (string) Setting::int('arrive_early_minutes', 10),
            '{batas_batal}' => (string) Setting::int('cancel_limit_hours', 3),
            '{studio}' => Setting::get('studio_name') ?: 'Active Barn',
            '{alamat}' => (string) Setting::get('studio_address', ''),
            '{telepon}' => (string) Setting::get('studio_phone', ''),
        ];
    }
}
