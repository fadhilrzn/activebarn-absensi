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
use App\Models\Trainer;
use App\Models\User;
use App\Services\AttendanceReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Waktu dibekukan 05 Okt 2026 12:00.
 * S1 09:00-10:00 (selesai): A hadir, B tidak hadir (ditandai), C tanpa catatan, D batal, E batal terlambat.
 * S2 15:00-16:00 (belum selesai): F aktif tanpa catatan.
 * S3 20 Sep 2026 (bulan lalu): G hadir.
 */
class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $counter = 0;
    private ClassSession $s1;
    private ClassSession $s2;
    private ClassSession $s3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Carbon::setTestNow('2026-10-05 12:00:00');
        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin@test.local', 'password' => 'rahasia123',
            'role' => UserRole::Admin, 'is_active' => true,
        ]);

        $this->s1 = $this->makeSession('2026-10-05 09:00:00', '2026-10-05 10:00:00', 'Hot Pilates', 'Amanda K.', 'Move Room');
        $this->s2 = $this->makeSession('2026-10-05 15:00:00', '2026-10-05 16:00:00', 'Zumba', 'Sarah W.', 'Beyond Room');
        $this->s3 = $this->makeSession('2026-09-20 09:00:00', '2026-09-20 10:00:00', 'Hot Pilates', 'Amanda K.', 'Move Room');

        $a = $this->makeBooking($this->s1, 'Ayu Hadir');
        Attendance::create(['booking_id' => $a->id, 'status' => AttendanceStatus::Hadir, 'checked_in_at' => '2026-10-05 08:55:00', 'method' => AttendanceMethod::Scan, 'recorded_by' => $this->admin->id]);
        $b = $this->makeBooking($this->s1, 'Budi Tandai');
        Attendance::create(['booking_id' => $b->id, 'status' => AttendanceStatus::TidakHadir]);
        $this->makeBooking($this->s1, 'Citra Belum Ditandai');
        $this->makeBooking($this->s1, 'Dina Batal', BookingStatus::Cancelled);
        $this->makeBooking($this->s1, 'Eka Terlambat', BookingStatus::LateCancelled);
        $this->makeBooking($this->s2, 'Fajar Belum');
        $g = $this->makeBooking($this->s3, 'Gita Lama');
        Attendance::create(['booking_id' => $g->id, 'status' => AttendanceStatus::Hadir, 'checked_in_at' => '2026-09-20 08:50:00', 'method' => AttendanceMethod::Manual, 'recorded_by' => $this->admin->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeSession(string $start, string $end, string $class, string $trainer, string $room): ClassSession
    {
        return ClassSession::create([
            'session_number' => ++$this->counter,
            'class_type_id' => ClassType::firstOrCreate(['name' => $class])->id,
            'trainer_id' => Trainer::firstOrCreate(['name' => $trainer])->id,
            'room_id' => Room::firstOrCreate(['name' => $room])->id,
            'starts_at' => $start,
            'ends_at' => $end,
        ]);
    }

    private function makeBooking(ClassSession $session, string $name, BookingStatus $status = BookingStatus::Active): Booking
    {
        $n = ++$this->counter;
        $member = Member::create(['name' => $name, 'phone' => '081200000' . str_pad((string) $n, 3, '0', STR_PAD_LEFT)]);

        return Booking::create([
            'member_id' => $member->id, 'class_session_id' => $session->id, 'source' => BookingSource::WhatsApp,
            'package_type' => 'Paket 5x', 'price' => 100000, 'payment_method' => 'Transfer',
            'status' => $status, 'created_by' => $this->admin->id,
        ]);
    }

    private function range(string $from, string $to, array $extra = []): array
    {
        return ['from' => $from, 'to' => $to] + $extra;
    }

    // ----------------------------------------------------------- hitungan

    public function test_ringkasan_menghitung_status_dari_data_tanpa_penjadwal(): void
    {
        $s = app(AttendanceReport::class)->summary($this->range('2026-10-01', '2026-10-31') + ['class_type' => null, 'trainer' => null]);

        $this->assertSame(4, $s['aktif']);            // A, B, C, F
        $this->assertSame(1, $s['hadir']);            // A
        $this->assertSame(2, $s['tidak_hadir']);      // B (ditandai) dan C (sesi selesai tanpa catatan)
        $this->assertSame(1, $s['belum']);            // F
        $this->assertSame(1, $s['batal']);
        $this->assertSame(1, $s['batal_terlambat']);
        $this->assertSame(33.3, $s['tingkat']);       // 1 / (1 + 2)
    }

    public function test_rentang_lebih_luas_menambah_data_bulan_lalu(): void
    {
        $s = app(AttendanceReport::class)->summary($this->range('2026-09-01', '2026-10-31') + ['class_type' => null, 'trainer' => null]);

        $this->assertSame(5, $s['aktif']);
        $this->assertSame(2, $s['hadir']);
        $this->assertSame(50.0, $s['tingkat']);       // 2 / (2 + 2)
    }

    public function test_rekap_per_kelas_dan_per_member(): void
    {
        $report = app(AttendanceReport::class);
        $f = $this->range('2026-10-01', '2026-10-31') + ['class_type' => null, 'trainer' => null];

        $byClass = $report->groups($f, 'kelas')->keyBy('label');
        $this->assertSame(3, $byClass['Hot Pilates']['aktif']);
        $this->assertSame(1, $byClass['Hot Pilates']['hadir']);
        $this->assertSame(2, $byClass['Hot Pilates']['tidak_hadir']);
        $this->assertSame(2, $byClass['Hot Pilates']['batal'] + $byClass['Hot Pilates']['batal_terlambat']);
        $this->assertSame(33.3, $byClass['Hot Pilates']['tingkat']);
        $this->assertNull($byClass['Zumba']['tingkat']);
        $this->assertSame(1, $byClass['Zumba']['belum']);

        $byMember = $report->groups($f, 'member')->keyBy('label');
        $this->assertSame(1, $byMember['Ayu Hadir']['hadir']);
        $this->assertSame(100.0, $byMember['Ayu Hadir']['tingkat']);
        $this->assertSame(0.0, $byMember['Citra Belum Ditandai']['tingkat']);

        $this->assertSame(3, $report->groups($f, 'trainer')->keyBy('label')['Amanda K.']['aktif']);
    }

    // ------------------------------------------------------------ halaman

    public function test_halaman_menampilkan_data_bulan_berjalan_dan_tingkat_kehadiran(): void
    {
        $this->actingAs($this->admin)
            ->get('/laporan-absensi')
            ->assertOk()
            ->assertSee('Ayu Hadir')->assertSee('Budi Tandai')->assertSee('Citra Belum Ditandai')
            ->assertSee('Dina Batal')->assertSee('Eka Terlambat')->assertSee('Fajar Belum')
            ->assertDontSee('Gita Lama')       // bulan lalu, di luar rentang bawaan
            ->assertSee('33,3%')
            ->assertSee('Ekspor Excel');
    }

    public function test_filter_status(): void
    {
        $this->actingAs($this->admin);

        $this->get('/laporan-absensi?status=tidak_hadir')->assertSee('Budi Tandai')->assertSee('Citra Belum Ditandai')
            ->assertDontSee('Ayu Hadir')->assertDontSee('Fajar Belum');
        $this->get('/laporan-absensi?status=belum')->assertSee('Fajar Belum')->assertDontSee('Citra Belum Ditandai');
        $this->get('/laporan-absensi?status=hadir')->assertSee('Ayu Hadir')->assertDontSee('Budi Tandai');
        $this->get('/laporan-absensi?status=batal')->assertSee('Dina Batal')->assertDontSee('Eka Terlambat');
        $this->get('/laporan-absensi?status=batal_terlambat')->assertSee('Eka Terlambat')->assertDontSee('Dina Batal');
    }

    public function test_filter_kelas_trainer_dan_rentang_tanggal(): void
    {
        $this->actingAs($this->admin);

        $zumba = ClassType::where('name', 'Zumba')->value('id');
        $this->get("/laporan-absensi?class_type={$zumba}")->assertSee('Fajar Belum')->assertDontSee('Ayu Hadir');

        $amanda = Trainer::where('name', 'Amanda K.')->value('id');
        $this->get("/laporan-absensi?trainer={$amanda}")->assertSee('Ayu Hadir')->assertDontSee('Fajar Belum');

        $this->get('/laporan-absensi?from=2026-09-20&to=2026-09-20')->assertSee('Gita Lama')->assertDontSee('Ayu Hadir');
        $this->get('/laporan-absensi?from=2026-10-06&to=2026-10-31')->assertSee('Tidak ada data yang cocok');
    }

    public function test_pengurutan(): void
    {
        $this->actingAs($this->admin);

        $this->get('/laporan-absensi?sort=member&dir=asc')
            ->assertSeeInOrder(['Ayu Hadir', 'Budi Tandai', 'Citra Belum Ditandai', 'Dina Batal']);
        $this->get('/laporan-absensi?sort=member&dir=desc')
            ->assertSeeInOrder(['Fajar Belum', 'Eka Terlambat', 'Dina Batal']);
    }

    public function test_rentang_terbalik_dan_status_tidak_dikenal_ditolak(): void
    {
        $this->actingAs($this->admin);

        $this->get('/laporan-absensi?from=2026-10-10&to=2026-10-01')->assertSessionHasErrors('to');
        $this->get('/laporan-absensi?status=ngawur')->assertSessionHasErrors('status');
        $this->get('/laporan-absensi?sort=ngawur')->assertSessionHasErrors('sort');
    }

    // -------------------------------------------------------------- ekspor

    public function test_ekspor_csv_berisi_data_terfilter_dan_mengamankan_rumus(): void
    {
        $this->makeBooking($this->s1, '=HYPERLINK("http://contoh.test")');
        $this->actingAs($this->admin);

        $response = $this->get('/laporan-absensi/ekspor/csv?from=2026-10-01&to=2026-10-31')->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('laporan-absensi_2026-10-01_2026-10-31.csv', $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Nama Member', $csv);
        $this->assertStringContainsString('Ayu Hadir', $csv);
        $this->assertStringContainsString('Hadir', $csv);
        $this->assertStringNotContainsString('Gita Lama', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);          // diberi apostrof
        $this->assertStringNotContainsString(';=HYPERLINK', $csv);

        $filtered = $this->get('/laporan-absensi/ekspor/csv?from=2026-10-01&to=2026-10-31&status=hadir')->streamedContent();
        $this->assertStringContainsString('Ayu Hadir', $filtered);
        $this->assertStringNotContainsString('Budi Tandai', $filtered);
    }

    public function test_ekspor_xlsx_dapat_dibuka_dan_isinya_benar(): void
    {
        $this->makeBooking($this->s1, '=HYPERLINK("http://contoh.test")');
        $this->actingAs($this->admin);

        $response = $this->get('/laporan-absensi/ekspor/xlsx?from=2026-10-01&to=2026-10-31')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'lap') . '.xlsx';
        file_put_contents($path, $response->streamedContent());

        try {
            $book = IOFactory::load($path);
            $this->assertSame(['Absensi', 'Ringkasan'], $book->getSheetNames());

            $sheet = $book->getSheetByName('Absensi');
            $this->assertSame('No', $sheet->getCell('A1')->getValue());
            $this->assertSame('Nama Member', $sheet->getCell('I1')->getValue());
            $this->assertSame(8, $sheet->getHighestRow());   // 1 judul + 6 booking + 1 baris rumus berbahaya

            $byName = [];
            for ($r = 2; $r <= 8; $r++) {
                $byName[$sheet->getCell("I{$r}")->getValue()] = $r;
            }
            $ayu = $byName['Ayu Hadir'];
            $this->assertSame('Hadir', $sheet->getCell("P{$ayu}")->getValue());
            $this->assertSame('Scan QR', $sheet->getCell("R{$ayu}")->getValue());
            $this->assertEquals(100000, $sheet->getCell("M{$ayu}")->getValue());
            $this->assertSame('s', $sheet->getCell("J{$ayu}")->getDataType());   // nomor WA tetap teks
            $this->assertSame('Tidak hadir', $sheet->getCell('P' . $byName['Citra Belum Ditandai'])->getValue());
            $this->assertSame('Batal terlambat', $sheet->getCell('P' . $byName['Eka Terlambat'])->getValue());

            $danger = $byName['=HYPERLINK("http://contoh.test")'];
            $this->assertSame('s', $sheet->getCell("I{$danger}")->getDataType());  // teks, bukan rumus

            $summary = $book->getSheetByName('Ringkasan');
            $this->assertSame('Laporan Absensi Active Barn', $summary->getCell('A1')->getValue());
        } finally {
            @unlink($path);
        }
    }

    public function test_format_ekspor_tidak_dikenal_ditolak(): void
    {
        $this->actingAs($this->admin)->get('/laporan-absensi/ekspor/pdf')->assertNotFound();
    }

    // --------------------------------------------------------------- akses

    public function test_resepsionis_tidak_boleh_membuka_laporan_maupun_ekspor(): void
    {
        $resepsionis = User::create([
            'name' => 'Resepsionis', 'email' => 'r@test.local', 'password' => 'rahasia123',
            'role' => UserRole::Resepsionis, 'is_active' => true,
        ]);

        $this->actingAs($resepsionis);
        $this->get('/laporan-absensi')->assertForbidden();
        $this->get('/laporan-absensi/ekspor/xlsx')->assertForbidden();
        $this->get('/laporan-absensi/ekspor/csv')->assertForbidden();
    }
}
