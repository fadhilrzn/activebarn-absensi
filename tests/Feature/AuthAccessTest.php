<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Member;
use App\Models\Room;
use App\Models\Trainer;
use App\Models\User;
use App\Services\CheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuthAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // tes tidak membutuhkan hasil build aset
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(UserRole $role, bool $active = true): User
    {
        return User::create([
            'name' => $role->label() . ' Uji',
            'email' => $role->value . '@test.local',
            'password' => 'rahasia123',
            'role' => $role,
            'is_active' => $active,
        ]);
    }

    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_halaman_login_tampil(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk');
    }

    public function test_login_berhasil_dengan_akun_aktif(): void
    {
        $this->makeUser(UserRole::Admin);

        $this->post('/login', ['email' => 'admin@test.local', 'password' => 'rahasia123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_login_gagal_dengan_kata_sandi_salah(): void
    {
        $this->makeUser(UserRole::Admin);

        $this->from('/login')
            ->post('/login', ['email' => 'admin@test.local', 'password' => 'salah'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_akun_nonaktif_tidak_bisa_masuk(): void
    {
        $this->makeUser(UserRole::Resepsionis, active: false);

        $this->post('/login', ['email' => 'resepsionis@test.local', 'password' => 'rahasia123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_resepsionis_boleh_dashboard_dan_scan_tetapi_tidak_halaman_admin(): void
    {
        $this->actingAs($this->makeUser(UserRole::Resepsionis));

        $this->get('/dashboard')->assertOk();
        $this->get('/scan-check-in')->assertOk();
        $this->get('/pengaturan')->assertForbidden();
        $this->get('/data-booking')->assertForbidden();
        $this->get('/laporan-absensi')->assertForbidden();
    }

    public function test_admin_boleh_semua_halaman(): void
    {
        $this->actingAs($this->makeUser(UserRole::Admin));

        foreach (['/dashboard', '/scan-check-in', '/data-booking', '/qr-whatsapp', '/laporan-absensi', '/pengaturan'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_dashboard_menampilkan_sesi_dan_aktivitas_scan(): void
    {
        Carbon::setTestNow('2026-10-05 09:00:00');
        $admin = $this->makeUser(UserRole::Admin);

        $session = ClassSession::create([
            'session_number' => 1,
            'class_type_id' => ClassType::create(['name' => 'Hot Pilates'])->id,
            'trainer_id' => Trainer::create(['name' => 'Amanda K.'])->id,
            'room_id' => Room::create(['name' => 'Move Room'])->id,
            'starts_at' => '2026-10-05 09:30:00',
            'ends_at' => '2026-10-05 10:30:00',
        ]);
        $member = Member::create(['name' => 'Siti Aminah', 'phone' => '081200000001']);
        $booking = Booking::create([
            'member_id' => $member->id,
            'class_session_id' => $session->id,
            'source' => BookingSource::WhatsApp,
            'price' => 100000,
            'created_by' => $admin->id,
        ]);

        app(CheckInService::class)->scan($booking->qr_token, $admin);

        $this->actingAs($admin)
            ->get('/dashboard?date=2026-10-05')
            ->assertOk()
            ->assertSee('Hot Pilates')
            ->assertSee('Amanda K.')
            ->assertSee('Siti Aminah')
            ->assertSee('Check-in berhasil');
    }

    public function test_tanggal_tidak_valid_ditolak(): void
    {
        $this->actingAs($this->makeUser(UserRole::Admin))
            ->get('/dashboard?date=bukan-tanggal')
            ->assertSessionHasErrors('date');
    }
}
