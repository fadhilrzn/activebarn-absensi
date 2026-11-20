<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\CarbonInterface;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Laporan kehadiran. Status kehadiran dihitung dari data, tidak bergantung
 * pada penjadwal "tandai tidak hadir":
 *
 *   batal           : booking dibatalkan tepat waktu
 *   batal_terlambat : member memberi kabar kurang dari batas jam sebelum kelas
 *   hadir           : ada catatan kehadiran berstatus hadir
 *   tidak_hadir     : catatan tidak_hadir, ATAU booking aktif tanpa catatan pada sesi yang sudah selesai
 *   belum           : booking aktif tanpa catatan pada sesi yang belum selesai
 *
 * Tingkat kehadiran = hadir / (hadir + tidak hadir). Batal dan belum tidak ikut dihitung.
 */
class AttendanceReport
{
    public const HADIR = 'hadir';
    public const TIDAK_HADIR = 'tidak_hadir';
    public const BELUM = 'belum';
    public const BATAL = 'batal';
    public const BATAL_TERLAMBAT = 'batal_terlambat';

    /** @var array<string, array{0:string, 1:string}> kunci => [label, warna badge] */
    public const STATES = [
        self::HADIR => ['Hadir', 'success'],
        self::TIDAK_HADIR => ['Tidak hadir', 'danger'],
        self::BELUM => ['Belum (sesi belum selesai)', 'neutral'],
        self::BATAL => ['Batal', 'neutral'],
        self::BATAL_TERLAMBAT => ['Batal terlambat', 'warning'],
    ];

    /** @var array<string, string> kunci urutan => kolom */
    public const SORTS = [
        'jadwal' => 'class_sessions.starts_at',
        'member' => 'members.name',
        'kelas' => 'class_types.name',
        'trainer' => 'trainers.name',
        'checkin' => 'attendances.checked_in_at',
    ];

    private const SQL_AKTIF = "SUM(CASE WHEN bookings.status = 'active' THEN 1 ELSE 0 END)";
    private const SQL_HADIR = "SUM(CASE WHEN bookings.status = 'active' AND attendances.status = 'hadir' THEN 1 ELSE 0 END)";
    private const SQL_TIDAK_HADIR = "SUM(CASE WHEN bookings.status = 'active' AND (attendances.status = 'tidak_hadir' OR (attendances.id IS NULL AND class_sessions.ends_at <= ?)) THEN 1 ELSE 0 END)";
    private const SQL_BELUM = "SUM(CASE WHEN bookings.status = 'active' AND attendances.id IS NULL AND class_sessions.ends_at > ? THEN 1 ELSE 0 END)";
    private const SQL_BATAL = "SUM(CASE WHEN bookings.status = 'cancelled' THEN 1 ELSE 0 END)";
    private const SQL_BATAL_TERLAMBAT = "SUM(CASE WHEN bookings.status = 'late_cancelled' THEN 1 ELSE 0 END)";

    /** Rentang bawaan: bulan berjalan. */
    public static function defaultRange(): array
    {
        return [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];
    }

    /**
     * @param  string|CarbonInterface  $endsAt
     */
    public static function stateKey(string $bookingStatus, ?string $attStatus, bool $hasAttendance, $endsAt, ?CarbonInterface $now = null): string
    {
        $now ??= now();

        return match (true) {
            $bookingStatus === 'cancelled' => self::BATAL,
            $bookingStatus === 'late_cancelled' => self::BATAL_TERLAMBAT,
            $attStatus === 'hadir' => self::HADIR,
            $attStatus === 'tidak_hadir' => self::TIDAK_HADIR,
            ! $hasAttendance && Carbon::parse($endsAt)->lessThanOrEqualTo($now) => self::TIDAK_HADIR,
            default => self::BELUM,
        };
    }

    /** Booking digabung dengan sesi, kelas, trainer, ruangan, member, dan kehadiran, lalu difilter. */
    public function query(array $f): Builder
    {
        return Booking::query()
            ->join('class_sessions', 'class_sessions.id', '=', 'bookings.class_session_id')
            ->join('class_types', 'class_types.id', '=', 'class_sessions.class_type_id')
            ->join('trainers', 'trainers.id', '=', 'class_sessions.trainer_id')
            ->join('rooms', 'rooms.id', '=', 'class_sessions.room_id')
            ->join('members', 'members.id', '=', 'bookings.member_id')
            ->leftJoin('attendances', 'attendances.booking_id', '=', 'bookings.id')
            ->where('class_sessions.starts_at', '>=', $f['from'] . ' 00:00:00')
            ->where('class_sessions.starts_at', '<=', $f['to'] . ' 23:59:59')
            ->when($f['class_type'] ?? null, fn ($q, $id) => $q->where('class_sessions.class_type_id', $id))
            ->when($f['trainer'] ?? null, fn ($q, $id) => $q->where('class_sessions.trainer_id', $id));
    }

    public function applyStatus(Builder $query, ?string $status, ?string $now = null): Builder
    {
        $now ??= $this->now();

        return match ($status) {
            self::HADIR => $query->where('bookings.status', 'active')->where('attendances.status', 'hadir'),
            self::TIDAK_HADIR => $query->where('bookings.status', 'active')->where(function ($w) use ($now) {
                $w->where('attendances.status', 'tidak_hadir')
                    ->orWhere(fn ($x) => $x->whereNull('attendances.id')->where('class_sessions.ends_at', '<=', $now));
            }),
            self::BELUM => $query->where('bookings.status', 'active')->whereNull('attendances.id')->where('class_sessions.ends_at', '>', $now),
            self::BATAL => $query->where('bookings.status', 'cancelled'),
            self::BATAL_TERLAMBAT => $query->where('bookings.status', 'late_cancelled'),
            default => $query,
        };
    }

    /** Kueri untuk tabel di layar (belum dipaginasi). */
    public function tableQuery(array $f): Builder
    {
        $column = self::SORTS[$f['sort'] ?? 'jadwal'] ?? self::SORTS['jadwal'];
        $dir = ($f['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $this->applyStatus($this->query($f), $f['status'] ?? null)
            ->select('bookings.*')
            ->orderBy($column, $dir)
            ->orderBy('bookings.id');
    }

    /** Ringkasan untuk kartu. Filter status tidak dipakai agar kartu selalu menampilkan seluruh rincian. */
    public function summary(array $f): array
    {
        [$sql, $bindings] = $this->metrics();
        $row = $this->query($f)->selectRaw($sql, $bindings)->toBase()->first();

        return $this->shape($row);
    }

    /**
     * Rekap per kelas, trainer, atau member (maks. 100 baris).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function groups(array $f, string $group): Collection
    {
        $map = [
            'kelas' => ['class_types.name', ['class_types.id', 'class_types.name']],
            'trainer' => ['trainers.name', ['trainers.id', 'trainers.name']],
            'member' => ['members.name', ['members.id', 'members.name', 'members.phone']],
        ];
        [$label, $groupBy] = $map[$group] ?? $map['kelas'];

        [$sql, $bindings] = $this->metrics();
        $select = "{$label} AS label, COUNT(DISTINCT class_sessions.id) AS sesi, {$sql}"
            . ($group === 'member' ? ', members.phone AS phone' : '');

        return $this->query($f)
            ->selectRaw($select, $bindings)
            ->groupBy(...$groupBy)
            ->orderByDesc('aktif')
            ->orderBy('label')
            ->limit(100)
            ->toBase()
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'phone' => $row->phone ?? null, 'sesi' => (int) $row->sesi] + $this->shape($row));
    }

    /** Baris untuk ekspor, dibaca satu per satu agar hemat memori. */
    public function exportRows(array $f): Generator
    {
        $query = $this->applyStatus($this->query($f), $f['status'] ?? null)
            ->selectRaw('class_sessions.session_number, class_sessions.starts_at, class_sessions.ends_at, '
                . 'class_types.name AS class_name, trainers.name AS trainer_name, rooms.name AS room_name, '
                . 'members.name AS member_name, members.phone AS phone, '
                . 'bookings.source AS source, bookings.package_type AS package_type, bookings.price AS price, '
                . 'bookings.payment_method AS payment_method, bookings.status AS booking_status, '
                . 'attendances.id AS att_id, attendances.status AS att_status, '
                . 'attendances.checked_in_at AS checked_in_at, attendances.method AS att_method')
            ->toBase()
            ->orderBy('class_sessions.starts_at')
            ->orderBy('members.name')
            ->orderBy('bookings.id');

        foreach ($query->cursor() as $row) {
            yield $row;
        }
    }

    /** @return array{0:string, 1:array<int,string>} */
    private function metrics(): array
    {
        $now = $this->now();
        $sql = implode(', ', [
            self::SQL_AKTIF . ' AS aktif',
            self::SQL_HADIR . ' AS hadir',
            self::SQL_TIDAK_HADIR . ' AS tidak_hadir',
            self::SQL_BELUM . ' AS belum',
            self::SQL_BATAL . ' AS batal',
            self::SQL_BATAL_TERLAMBAT . ' AS batal_terlambat',
        ]);

        // Urutan pengikat mengikuti urutan tanda tanya: tidak_hadir lalu belum.
        return [$sql, [$now, $now]];
    }

    /** @return array{aktif:int, hadir:int, tidak_hadir:int, belum:int, batal:int, batal_terlambat:int, tingkat:?float} */
    private function shape(?object $row): array
    {
        $s = [
            'aktif' => (int) ($row->aktif ?? 0),
            'hadir' => (int) ($row->hadir ?? 0),
            'tidak_hadir' => (int) ($row->tidak_hadir ?? 0),
            'belum' => (int) ($row->belum ?? 0),
            'batal' => (int) ($row->batal ?? 0),
            'batal_terlambat' => (int) ($row->batal_terlambat ?? 0),
        ];
        $denominator = $s['hadir'] + $s['tidak_hadir'];
        $s['tingkat'] = $denominator > 0 ? round($s['hadir'] / $denominator * 100, 1) : null;

        return $s;
    }

    private function now(): string
    {
        return now()->toDateTimeString();
    }
}
