<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi template pesan WhatsApp dan jeda kedatangan.
 * Teks dibekukan di sini (tidak merujuk kelas aplikasi) agar migrasi
 * tetap aman walaupun kode berubah di kemudian hari.
 */
return new class extends Migration
{
    private const TEMPLATE = <<<'TXT'
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

    public function up(): void
    {
        $now = now();

        foreach (['whatsapp_template' => self::TEMPLATE, 'arrive_early_minutes' => '10'] as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => $now, 'created_at' => $now],
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'arrive_early_minutes')->delete();
    }
};
