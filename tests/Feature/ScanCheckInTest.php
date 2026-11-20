<?php

namespace Tests\Feature;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Member;
use App\Models\Room;
use App\Models\ScanLog;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Waktu dibekukan 05 Okt 2026 09:00; sesi standar 09:30-10:30 (check-in dibuka 09:00). */
class ScanCheckInTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->staff = User::create([
            'name' => 'Resepsionis', 'email' => 'r@test.local', 'password' => 'rahasia123',
            'role' => UserRole::Resepsionis, 'is_active' => true,
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
            'price' => 100000, 'status' => $status, 'created_by' => $this->staff->id,
        ]);
    }

    // -------------------------------------------------------------- scan JSON

    public function test_scan_berhasil_mengembalikan_data_member_dan_mencatat_kehadiran(): void
    {
        $booking = $this->makeBooking($this->makeSession());

        $this->actingAs($this->staff)
            ->postJson('/scan-check-in/scan', ['token' => $booking->qr_token])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result', 'success')
            ->assertJsonPath('title', 'Check-In Berhasil')
            ->assertJsonPath('booking.member', 'Siti Aminah')
            ->assertJsonPath('booking.class', 'Hot Pilates')
            ->assertJsonPath('booking.trainer', 'Amanda K.')
            ->assertJsonPath('booking.code', $booking->booking_code);

        $attendance = Attendance::firstOrFail();
        $this->assertSame(AttendanceStatus::Hadir, $attendance->status);
        $this->assertSame(AttendanceMethod::Scan, $attendance->method);
        $this->assertSame($this->staff->id, $attendance->recorded_by);
    }

    public function test_qr_tidak_dikenali_ditolak(): void
    {
        $this->actingAs($this->staff)
            ->postJson('/scan-check-in/scan', ['token' => 'token-ngawur'])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('result', 'invalid_qr')
            ->assertJsonPath('booking', null);

        $this->assertSame(0, Attendance::count());
        $this->assertSame(1, ScanLog::count());
    }

    public function test_scan_kedua_untuk_booking_yang_sama_ditolak_sebagai_duplikat(): void
    {
        $booking = $this->makeBooking($this->makeSession());
        $this->actingAs($this->staff);

        $this->postJson('/scan-check-in/scan', ['token' => $booking->qr_token])->assertJsonPath('success', true);
        $this->postJson('/scan-check-in/scan', ['token' => $booking->qr_token])
            ->assertJsonPath('success', false)
            ->assertJsonPath('result', 'duplicate')
            ->assertJsonPath('booking.member', 'Siti Aminah');

        $this->assertSame(1, Attendance::count());
    }

    public function test_scan_di_luar_jadwal_ditolak(): void
    {
        $booking = $this->makeBooking($this->makeSession('2026-10-05 12:00:00', '2026-10-05 13:00:00'));

        $this->actingAs($this->staff)
            ->postJson('/scan-check-in/scan', ['token' => $booking->qr_token])
            ->assertJsonPath('result', 'outside_window');

        $this->assertSame(0, Attendance::count());
    }

    public function test_scan_booking_batal_ditolak(): void
    {
        $booking = $this->makeBooking($this->makeSession(), status: BookingStatus::LateCancelled);

        $this->actingAs($this->staff)
            ->postJson('/scan-check-in/scan', ['token' => $booking->qr_token])
            ->assertJsonPath('result', 'cancelled')
            ->assertJsonPath('title', 'Booking Dibatalkan');
    }

    public function test_token_wajib_diisi_dan_tamu_tidak_boleh_scan(): void
    {
        $this->actingAs($this->staff)->postJson('/scan-check-in/scan', [])->assertUnprocessable();

        auth()->logout();
        $this->postJson('/scan-check-in/scan', ['token' => 'x'])->assertUnauthorized();
    }

    // ----------------------------------------------------------- halaman web

    public function test_halaman_scan_tampil_untuk_resepsionis_dan_menampilkan_sesi_yang_sedang_dibuka(): void
    {
        $this->makeBooking($this->makeSession(), 'Siti Aminah');
        $this->makeBooking($this->makeSession('2026-10-05 12:00:00', '2026-10-05 13:00:00'), 'Budi Santoso');

        $this->actingAs($this->staff)
            ->get('/scan-check-in')
            ->assertOk()
            ->assertSee('Mulai Scan')
            ->assertSee('Siti Aminah')           // sesi 09:30, check-in sudah dibuka
            ->assertDontSee('Budi Santoso');     // sesi 12:00 belum dibuka
    }

    public function test_pencarian_menemukan_booking_hari_ini_lewat_nama_kode_dan_nomor(): void
    {
        $siti = $this->makeBooking($this->makeSession(), 'Siti Aminah');
        $this->makeBooking($this->makeSession('2026-10-05 12:00:00', '2026-10-05 13:00:00'), 'Budi Santoso');
        $this->makeBooking($this->makeSession('2026-10-06 09:30:00', '2026-10-06 10:30:00'), 'Besok Hadir');
        $this->makeBooking($this->makeSession('2026-10-05 15:00:00', '2026-10-05 16:00:00'), 'Dina Batal', BookingStatus::Cancelled);

        $this->actingAs($this->staff);
        $this->get('/scan-check-in?q=Budi')->assertSee('Budi Santoso')->assertDontSee('Siti Aminah');
        $this->get('/scan-check-in?q=' . $siti->booking_code)->assertSee('Siti Aminah')->assertDontSee('Budi Santoso');
        $this->get('/scan-check-in?q=' . substr($siti->member->phone, 2))->assertSee('Siti Aminah');
        $this->get('/scan-check-in?q=Besok')->assertDontSee('Besok Hadir');   // bukan hari ini
        $this->get('/scan-check-in?q=Dina')->assertDontSee('Dina Batal');     // booking batal tidak muncul
        $this->get('/scan-check-in?q=tidakadayangcocok')->assertSee('Tidak ada booking aktif hari ini');
    }

    public function test_check_in_manual_mencatat_metode_manual_dan_menampilkan_hasil(): void
    {
        $booking = $this->makeBooking($this->makeSession());

        $this->actingAs($this->staff)
            ->post(route('scan.manual', $booking), ['q' => 'Siti'])
            ->assertRedirect(route('scan.index', ['q' => 'Siti']))
            ->assertSessionHas('checkin.success', true);

        $attendance = Attendance::firstOrFail();
        $this->assertSame(AttendanceMethod::Manual, $attendance->method);

        $this->get(route('scan.index', ['q' => 'Siti']))
            ->assertOk()
            ->assertSee('Check-In Berhasil')
            ->assertSee('Siti Aminah');
    }

    public function test_check_in_manual_di_luar_jadwal_ditolak_dan_pesannya_tampil(): void
    {
        $booking = $this->makeBooking($this->makeSession('2026-10-05 12:00:00', '2026-10-05 13:00:00'));

        $this->actingAs($this->staff)
            ->post(route('scan.manual', $booking))
            ->assertSessionHas('checkin.success', false);

        $this->get(route('scan.index'))->assertSee('Di Luar Jadwal Check-In');
        $this->assertSame(0, Attendance::count());
    }
}
