<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Member;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Trainer;
use App\Models\User;
use App\Services\BookingCancellationService;
use App\Services\CheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pengujian Equivalence Partitioning dan Boundary Value Analysis (Bab 2.1.4).
 *
 * Aturan 1, jendela check-in: kelas 09:30-10:30, jendela dibuka 30 menit sebelum mulai
 * dan ditutup saat kelas selesai. Kedua batas bersifat inklusif.
 * Aturan 2, pembatalan: kelas 12:00, batas 3 jam. Kabar yang masuk tepat pada batas
 * masih tergolong batal biasa; satu detik sesudahnya batal terlambat.
 */
class BoundaryValueTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function makeBooking(string $start, string $end): Booking
    {
        $session = ClassSession::create([
            'session_number' => 1,
            'class_type_id' => ClassType::create(['name' => 'Hot Pilates'])->id,
            'trainer_id' => Trainer::create(['name' => 'Amanda K.'])->id,
            'room_id' => Room::create(['name' => 'Move Room'])->id,
            'starts_at' => $start,
            'ends_at' => $end,
        ]);
        $member = Member::create(['name' => 'Siti Aminah', 'phone' => '081200000001']);

        return Booking::create([
            'member_id' => $member->id, 'class_session_id' => $session->id, 'source' => BookingSource::WhatsApp,
            'price' => 100000, 'status' => BookingStatus::Active, 'created_by' => $this->staff->id,
        ]);
    }

    // ------------------------------------------------ jendela check-in (30 menit)

    /** @return array<string, array{0:string, 1:string}> */
    public static function jendelaCheckIn(): array
    {
        return [
            // partisi tidak valid: sebelum jendela
            '1 menit sebelum jendela dibuka' => ['2026-10-05 08:59:00', 'outside_window'],
            '1 detik sebelum jendela dibuka' => ['2026-10-05 08:59:59', 'outside_window'],
            // batas bawah dan sekitarnya
            'tepat 30 menit sebelum kelas' => ['2026-10-05 09:00:00', 'success'],
            '1 menit sesudah jendela dibuka' => ['2026-10-05 09:01:00', 'success'],
            // partisi valid: di dalam jendela
            'tepat saat kelas dimulai' => ['2026-10-05 09:30:00', 'success'],
            'di tengah kelas' => ['2026-10-05 10:00:00', 'success'],
            // batas atas dan sekitarnya
            '1 menit sebelum kelas selesai' => ['2026-10-05 10:29:00', 'success'],
            'tepat saat kelas selesai' => ['2026-10-05 10:30:00', 'success'],
            '1 detik sesudah kelas selesai' => ['2026-10-05 10:30:01', 'outside_window'],
            // partisi tidak valid: sesudah kelas
            '1 menit sesudah kelas selesai' => ['2026-10-05 10:31:00', 'outside_window'],
        ];
    }

    #[DataProvider('jendelaCheckIn')]
    public function test_batas_jendela_check_in(string $waktuScan, string $hasilDiharapkan): void
    {
        $booking = $this->makeBooking('2026-10-05 09:30:00', '2026-10-05 10:30:00');
        Carbon::setTestNow($waktuScan);

        $result = app(CheckInService::class)->scan($booking->qr_token, $this->staff);

        $this->assertSame($hasilDiharapkan, $result->result->value);
        $this->assertSame($hasilDiharapkan === 'success', $result->success);
    }

    public function test_jendela_check_in_mengikuti_pengaturan_menit(): void
    {
        Setting::set('checkin_open_minutes', '15');
        $booking = $this->makeBooking('2026-10-05 09:30:00', '2026-10-05 10:30:00');
        $service = app(CheckInService::class);

        Carbon::setTestNow('2026-10-05 09:14:59');
        $this->assertSame('outside_window', $service->scan($booking->qr_token, $this->staff)->result->value);

        Carbon::setTestNow('2026-10-05 09:15:00');
        $this->assertTrue($service->scan($booking->qr_token, $this->staff)->success);
    }

    // ------------------------------------------------ pembatalan (3 jam)

    /** @return array<string, array{0:string, 1:string}> */
    public static function batasPembatalan(): array
    {
        return [
            // partisi batal biasa: kabar paling lambat 3 jam sebelum kelas
            '1 jam sebelum batas' => ['2026-10-05 08:00:00', 'cancelled'],
            '1 detik sebelum batas' => ['2026-10-05 08:59:59', 'cancelled'],
            'tepat 3 jam sebelum kelas' => ['2026-10-05 09:00:00', 'cancelled'],
            // partisi batal terlambat: kurang dari 3 jam sebelum kelas
            '1 detik sesudah batas' => ['2026-10-05 09:00:01', 'late_cancelled'],
            '1 menit sesudah batas' => ['2026-10-05 09:01:00', 'late_cancelled'],
            '1 jam sebelum kelas' => ['2026-10-05 11:00:00', 'late_cancelled'],
            'tepat saat kelas dimulai' => ['2026-10-05 12:00:00', 'late_cancelled'],
            'setelah kelas dimulai' => ['2026-10-05 12:30:00', 'late_cancelled'],
        ];
    }

    #[DataProvider('batasPembatalan')]
    public function test_batas_pembatalan_tiga_jam(string $waktuKabar, string $statusDiharapkan): void
    {
        $booking = $this->makeBooking('2026-10-05 12:00:00', '2026-10-05 13:00:00');

        (new BookingCancellationService())->cancel($booking, Carbon::parse($waktuKabar));

        $this->assertSame($statusDiharapkan, $booking->fresh()->status->value);
    }

    public function test_batas_pembatalan_mengikuti_pengaturan_jam(): void
    {
        Setting::set('cancel_limit_hours', '2'); // batas menjadi 10:00
        $service = new BookingCancellationService();

        $tepat = $this->makeBooking('2026-10-05 12:00:00', '2026-10-05 13:00:00');
        $service->cancel($tepat, Carbon::parse('2026-10-05 10:00:00'));
        $this->assertSame('cancelled', $tepat->fresh()->status->value);

        $telat = Booking::create([
            'member_id' => Member::create(['name' => 'Budi', 'phone' => '081200000002'])->id,
            'class_session_id' => $tepat->class_session_id, 'source' => BookingSource::WhatsApp,
            'price' => 100000, 'status' => BookingStatus::Active, 'created_by' => $this->staff->id,
        ]);
        $service->cancel($telat, Carbon::parse('2026-10-05 10:00:01'));
        $this->assertSame('late_cancelled', $telat->fresh()->status->value);
    }
}
