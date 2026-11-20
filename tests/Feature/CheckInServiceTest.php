<?php

namespace Tests\Feature;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\ScanResult;
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
use App\Services\BookingCancellationService;
use App\Services\CheckInService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Waktu uji dibekukan pada 05 Okt 2026 pukul 09:00.
 * Sesi standar: 09:30-10:30 -> check-in dibuka 09:00 (30 menit sebelum).
 */
class CheckInServiceTest extends TestCase
{
    use RefreshDatabase;

    private CheckInService $service;
    private User $staff;
    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->service = app(CheckInService::class);
        $this->staff = User::create([
            'name' => 'Resepsionis',
            'email' => 'resepsionis@test.local',
            'password' => 'secret-password',
            'role' => UserRole::Resepsionis,
            'is_active' => true,
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

    private function makeBooking(ClassSession $session, BookingStatus $status = BookingStatus::Active): Booking
    {
        $n = ++$this->counter;
        $member = Member::create(['name' => "Member {$n}", 'phone' => '081200000' . str_pad((string) $n, 3, '0', STR_PAD_LEFT)]);

        return Booking::create([
            'member_id' => $member->id,
            'class_session_id' => $session->id,
            'source' => BookingSource::WhatsApp,
            'price' => 100000,
            'status' => $status,
            'created_by' => $this->staff->id,
        ]);
    }

    public function test_scan_tepat_saat_jendela_dibuka_berhasil(): void
    {
        $booking = $this->makeBooking($this->makeSession());

        $result = $this->service->scan($booking->qr_token, $this->staff);

        $this->assertTrue($result->success);
        $attendance = Attendance::where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame(AttendanceStatus::Hadir, $attendance->status);
        $this->assertSame(AttendanceMethod::Scan, $attendance->method);
        $this->assertSame($this->staff->id, $attendance->recorded_by);
        $this->assertSame(ScanResult::Success, ScanLog::firstOrFail()->result);
    }

    public function test_qr_tidak_dikenali_ditolak_dan_dicatat(): void
    {
        $result = $this->service->scan('token-ngawur', $this->staff);

        $this->assertFalse($result->success);
        $this->assertSame(ScanResult::InvalidQr, $result->result);
        $this->assertSame(0, Attendance::count());
        $this->assertSame(ScanResult::InvalidQr, ScanLog::firstOrFail()->result);
    }

    public function test_scan_sebelum_jendela_dibuka_ditolak(): void
    {
        $booking = $this->makeBooking($this->makeSession());
        Carbon::setTestNow('2026-10-05 08:59:00');

        $result = $this->service->scan($booking->qr_token, $this->staff);

        $this->assertSame(ScanResult::OutsideWindow, $result->result);
        $this->assertSame(0, Attendance::count());
    }

    public function test_scan_setelah_kelas_selesai_ditolak(): void
    {
        $booking = $this->makeBooking($this->makeSession());
        Carbon::setTestNow('2026-10-05 10:31:00');

        $result = $this->service->scan($booking->qr_token, $this->staff);

        $this->assertSame(ScanResult::OutsideWindow, $result->result);
        $this->assertSame(0, Attendance::count());
    }

    public function test_scan_ganda_ditolak_dan_hanya_satu_catatan(): void
    {
        $booking = $this->makeBooking($this->makeSession());

        $first = $this->service->scan($booking->qr_token, $this->staff);
        $second = $this->service->scan($booking->qr_token, $this->staff);

        $this->assertTrue($first->success);
        $this->assertSame(ScanResult::Duplicate, $second->result);
        $this->assertSame(1, Attendance::count());
        $this->assertSame(2, ScanLog::count());
    }

    public function test_booking_batal_ditolak(): void
    {
        $booking = $this->makeBooking($this->makeSession(), BookingStatus::Cancelled);

        $result = $this->service->scan($booking->qr_token, $this->staff);

        $this->assertSame(ScanResult::Cancelled, $result->result);
        $this->assertSame(0, Attendance::count());
    }

    public function test_check_in_manual_tercatat_dengan_metode_manual(): void
    {
        $booking = $this->makeBooking($this->makeSession());

        $result = $this->service->manual($booking, $this->staff, 'QR tidak terbaca');

        $this->assertTrue($result->success);
        $attendance = Attendance::firstOrFail();
        $this->assertSame(AttendanceMethod::Manual, $attendance->method);
        $this->assertSame('QR tidak terbaca', $attendance->note);
    }

    public function test_tandai_tidak_hadir_hanya_booking_aktif_tanpa_kehadiran(): void
    {
        $session = $this->makeSession('2026-10-05 07:00:00', '2026-10-05 08:00:00'); // sudah selesai
        $absen = $this->makeBooking($session);
        $batal = $this->makeBooking($session, BookingStatus::Cancelled);
        $hadir = $this->makeBooking($session);
        Attendance::create([
            'booking_id' => $hadir->id,
            'status' => AttendanceStatus::Hadir,
            'checked_in_at' => '2026-10-05 06:50:00',
            'method' => AttendanceMethod::Scan,
        ]);

        $this->assertSame(1, $this->service->markAbsent());
        $this->assertSame(AttendanceStatus::TidakHadir, Attendance::where('booking_id', $absen->id)->firstOrFail()->status);
        $this->assertNull(Attendance::where('booking_id', $batal->id)->first());
        $this->assertSame(AttendanceStatus::Hadir, Attendance::where('booking_id', $hadir->id)->firstOrFail()->status);

        // Idempoten: dijalankan lagi tidak menambah apa pun.
        $this->assertSame(0, $this->service->markAbsent());
    }

    public function test_sesi_yang_belum_selesai_tidak_ditandai_tidak_hadir(): void
    {
        $this->makeBooking($this->makeSession()); // 09:30-10:30, sekarang 09:00

        $this->assertSame(0, $this->service->markAbsent());
    }

    public function test_pembatalan_lebih_dari_3_jam_sebelum_kelas_adalah_batal_biasa(): void
    {
        $booking = $this->makeBooking($this->makeSession()); // mulai 09:30, batas 06:30

        (new BookingCancellationService())->cancel($booking, Carbon::parse('2026-10-05 06:00:00'));

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
    }

    public function test_pembatalan_kurang_dari_3_jam_sebelum_kelas_adalah_batal_terlambat(): void
    {
        $booking = $this->makeBooking($this->makeSession());

        (new BookingCancellationService())->cancel($booking, Carbon::parse('2026-10-05 07:00:00'));

        $this->assertSame(BookingStatus::LateCancelled, $booking->fresh()->status);
    }

    public function test_booking_yang_sudah_check_in_tidak_bisa_dibatalkan(): void
    {
        $booking = $this->makeBooking($this->makeSession());
        $this->service->scan($booking->qr_token, $this->staff);

        $this->expectException(DomainException::class);
        (new BookingCancellationService())->cancel($booking);
    }
}
