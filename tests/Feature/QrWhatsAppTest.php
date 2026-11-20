<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Member;
use App\Models\Room;
use App\Models\Trainer;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\WhatsAppMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Waktu dibekukan 05 Okt 2026 08:00; sesi standar 09:30-10:30. */
class QrWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Carbon::setTestNow('2026-10-05 08:00:00');
        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin@test.local', 'password' => 'rahasia123',
            'role' => UserRole::Admin, 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeSession(string $start = '2026-10-05 09:30:00', string $end = '2026-10-05 10:30:00'): ClassSession
    {
        return ClassSession::create([
            'session_number' => ++$this->counter,
            'class_type_id' => ClassType::firstOrCreate(['name' => 'Hot Pilates'])->id,
            'trainer_id' => Trainer::firstOrCreate(['name' => 'Amanda K.'])->id,
            'room_id' => Room::firstOrCreate(['name' => 'Move Room'])->id,
            'starts_at' => $start,
            'ends_at' => $end,
        ]);
    }

    private function makeBooking(ClassSession $session, string $name = 'Siti Aminah', BookingStatus $status = BookingStatus::Active): Booking
    {
        $n = ++$this->counter;
        $member = Member::create(['name' => $name, 'phone' => '081200000' . str_pad((string) $n, 3, '0', STR_PAD_LEFT)]);

        return Booking::create([
            'member_id' => $member->id, 'class_session_id' => $session->id, 'source' => BookingSource::WhatsApp,
            'price' => 100000, 'status' => $status, 'created_by' => $this->admin->id,
        ]);
    }

    // ----------------------------------------------------------- pesan & tautan

    public function test_pesan_terisi_dari_template_tanpa_placeholder_tersisa(): void
    {
        $booking = $this->makeBooking($this->makeSession());

        $message = app(WhatsAppMessageService::class)->render($booking);

        $this->assertStringContainsString('QR Check-In Kamu Sudah Siap!', $message);
        $this->assertStringContainsString('Halo, Siti Aminah!', $message);
        $this->assertStringContainsString('Kelas: Hot Pilates', $message);
        $this->assertStringContainsString('Instruktur: Amanda K.', $message);
        $this->assertStringContainsString('Studio: Active Barn - Move Room', $message);
        $this->assertStringContainsString('2026 | 09:30', $message);
        $this->assertStringContainsString('Kode Booking: ' . $booking->booking_code, $message);
        $this->assertStringContainsString('tiba 10 menit', $message);
        $this->assertStringContainsString('paling lambat 3 jam', $message);
        $this->assertStringNotContainsString('{', $message);
    }

    public function test_tautan_whatsapp_memakai_nomor_internasional_dan_teks_tersandi(): void
    {
        $booking = $this->makeBooking($this->makeSession(), 'Dewi & Rina');
        $service = app(WhatsAppMessageService::class);

        $link = $service->link($booking);

        $this->assertStringStartsWith('https://wa.me/' . $booking->member->phone . '?text=', $link);
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
        $this->assertSame($service->render($booking), $query['text']);
    }

    // --------------------------------------------------------------- halaman

    public function test_daftar_menampilkan_booking_aktif_pada_tanggal_dan_status_qr(): void
    {
        $sent = $this->makeBooking($this->makeSession(), 'Siti Aminah');
        $sent->update(['qr_sent_at' => now(), 'qr_sent_by' => $this->admin->id]);
        $this->makeBooking($this->makeSession('2026-10-05 12:00:00', '2026-10-05 13:00:00'), 'Budi Santoso');
        $this->makeBooking($this->makeSession('2026-10-06 09:30:00', '2026-10-06 10:30:00'), 'Besok Hadir');
        $this->makeBooking($this->makeSession('2026-10-05 15:00:00', '2026-10-05 16:00:00'), 'Dina Batal', BookingStatus::Cancelled);

        $this->actingAs($this->admin);
        $this->get('/qr-whatsapp')->assertOk()
            ->assertSee('Siti Aminah')->assertSee('Budi Santoso')
            ->assertDontSee('Besok Hadir')->assertDontSee('Dina Batal')
            ->assertSee('Mulai antrean')
            ->assertSee('oleh Admin Uji'); // pengirim tercatat di kolom status

        $this->get('/qr-whatsapp?qr=belum')->assertSee('Budi Santoso')->assertDontSee('Siti Aminah');
        $this->get('/qr-whatsapp?qr=terkirim')->assertSee('Siti Aminah')->assertDontSee('Budi Santoso');
        $this->get('/qr-whatsapp?date=2026-10-06')->assertSee('Besok Hadir')->assertDontSee('Siti Aminah');
        $this->get('/qr-whatsapp?date=salah')->assertSessionHasErrors('date');
    }

    public function test_halaman_kirim_menampilkan_qr_pesan_dan_tautan_whatsapp(): void
    {
        $booking = $this->makeBooking($this->makeSession());

        $this->actingAs($this->admin)
            ->get(route('qr.show', $booking))
            ->assertOk()
            ->assertSee('Siti Aminah')
            ->assertSee('QR Check-In Kamu Sudah Siap!')
            ->assertSee('https://wa.me/' . $booking->member->phone, false)
            ->assertSee(route('qr.image', $booking), false)
            ->assertSee('Unduh QR');
    }

    public function test_gambar_qr_berupa_svg_dan_booking_batal_tidak_dibuatkan(): void
    {
        $active = $this->makeBooking($this->makeSession());
        $cancelled = $this->makeBooking($this->makeSession('2026-10-05 12:00:00', '2026-10-05 13:00:00'), 'Dina', BookingStatus::Cancelled);

        $this->actingAs($this->admin);
        $response = $this->get(route('qr.image', $active))->assertOk();
        $this->assertStringContainsString('image/svg+xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $response->getContent());

        $this->get(route('qr.image', $cancelled))->assertNotFound();
        $this->get(route('qr.show', $cancelled))->assertRedirect(route('qr.index'));
    }

    // ------------------------------------------------------ tandai terkirim

    public function test_tandai_terkirim_mencatat_waktu_dan_pengirim_lalu_lanjut_ke_berikutnya(): void
    {
        $session = $this->makeSession();
        $first = $this->makeBooking($session, 'Siti Aminah');
        $second = $this->makeBooking($session, 'Rina Putri');

        $this->actingAs($this->admin)
            ->post(route('qr.sent', $first))
            ->assertRedirect(route('qr.show', $second));

        $first->refresh();
        $this->assertNotNull($first->qr_sent_at);
        $this->assertSame($this->admin->id, $first->qr_sent_by);

        // Booking terakhir: kembali ke daftar dengan tanggal yang sama.
        $this->post(route('qr.sent', $second))
            ->assertRedirect(route('qr.index', ['date' => '2026-10-05']));
        $this->assertNotNull($second->fresh()->qr_sent_at);
    }

    public function test_tidak_bisa_menandai_terkirim_untuk_booking_batal_atau_kelas_selesai(): void
    {
        $cancelled = $this->makeBooking($this->makeSession(), 'Dina', BookingStatus::Cancelled);
        $ended = $this->makeBooking($this->makeSession('2026-10-05 06:00:00', '2026-10-05 07:00:00'), 'Lama');

        $this->actingAs($this->admin);
        $this->post(route('qr.sent', $cancelled))->assertSessionHasErrors('qr');
        $this->post(route('qr.sent', $ended))->assertSessionHasErrors('qr');

        $this->assertNull($cancelled->fresh()->qr_sent_at);
        $this->assertNull($ended->fresh()->qr_sent_at);
    }

    // ----------------------------------------------------------- buat ulang

    public function test_buat_ulang_menonaktifkan_qr_lama_dan_qr_baru_berfungsi(): void
    {
        $booking = $this->makeBooking($this->makeSession());
        $booking->update(['qr_sent_at' => now(), 'qr_sent_by' => $this->admin->id]);
        $oldToken = $booking->qr_token;

        $this->actingAs($this->admin)
            ->post(route('qr.regenerate', $booking))
            ->assertRedirect(route('qr.show', $booking));

        $booking->refresh();
        $this->assertNotSame($oldToken, $booking->qr_token);
        $this->assertNull($booking->qr_sent_at); // perlu dikirim ulang

        // Jendela check-in dibuka 09:00; pindahkan waktu ke sana.
        Carbon::setTestNow('2026-10-05 09:05:00');
        $checkIn = app(CheckInService::class);

        $this->assertSame('invalid_qr', $checkIn->scan($oldToken, $this->admin)->result->value);
        $this->assertTrue($checkIn->scan($booking->qr_token, $this->admin)->success);
    }

    public function test_buat_ulang_ditolak_jika_member_sudah_check_in(): void
    {
        $booking = $this->makeBooking($this->makeSession());
        Carbon::setTestNow('2026-10-05 09:05:00');
        app(CheckInService::class)->scan($booking->qr_token, $this->admin);
        $token = $booking->qr_token;

        $this->actingAs($this->admin)
            ->post(route('qr.regenerate', $booking))
            ->assertSessionHasErrors('qr');

        $this->assertSame($token, $booking->fresh()->qr_token);
        $this->assertSame(1, Attendance::count());
    }

    // ----------------------------------------------------------------- akses

    public function test_resepsionis_tidak_boleh_mengakses_modul_qr(): void
    {
        $booking = $this->makeBooking($this->makeSession());
        $resepsionis = User::create([
            'name' => 'Resepsionis', 'email' => 'r@test.local', 'password' => 'rahasia123',
            'role' => UserRole::Resepsionis, 'is_active' => true,
        ]);

        $this->actingAs($resepsionis);
        $this->get('/qr-whatsapp')->assertForbidden();
        $this->get(route('qr.show', $booking))->assertForbidden();
        $this->get(route('qr.image', $booking))->assertForbidden();
        $this->post(route('qr.sent', $booking))->assertForbidden();
        $this->post(route('qr.regenerate', $booking))->assertForbidden();
    }
}
