<?php

namespace App\Services;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Enums\ScanResult;
use App\Models\Attendance;
use App\Models\Booking;
use App\Models\ScanLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Aturan check-in:
 *  1. Token QR harus dikenali.
 *  2. Booking harus masih aktif (belum dibatalkan).
 *  3. Member belum check-in (tidak boleh ganda; dijamin juga oleh
 *     unique constraint attendances.booking_id).
 *  4. Waktu scan harus di dalam jendela check-in:
 *     [mulai kelas - N menit, selesai kelas]  (N dari tabel settings).
 *
 * Setiap percobaan, berhasil maupun gagal, dicatat di scan_logs.
 */
class CheckInService
{
    /** Check-in lewat scan QR. */
    public function scan(string $token, User $scanner, ?CarbonInterface $now = null): CheckInResult
    {
        $token = trim($token);
        $booking = $token === '' ? null : Booking::where('qr_token', $token)->first();

        return $this->attempt($booking, $token, $scanner, AttendanceMethod::Scan, null, $now ?? now());
    }

    /** Check-in manual (cadangan jika QR tidak bisa dibaca). Aturannya sama dengan scan. */
    public function manual(Booking $booking, User $scanner, ?string $note = null, ?CarbonInterface $now = null): CheckInResult
    {
        return $this->attempt($booking, null, $scanner, AttendanceMethod::Manual, $note, $now ?? now());
    }

    /**
     * Tandai "tidak hadir" untuk booking AKTIF pada sesi yang sudah selesai
     * dan belum punya catatan kehadiran. Aman dijalankan berulang.
     *
     * @return int jumlah booking yang baru ditandai
     */
    public function markAbsent(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $count = 0;

        Booking::active()
            ->whereDoesntHave('attendance')
            ->whereHas('classSession', fn ($q) => $q->endedBefore($now))
            ->select('id')
            ->chunkById(200, function ($bookings) use (&$count, $now) {
                $rows = $bookings->map(fn ($b) => [
                    'booking_id' => $b->id,
                    'status' => AttendanceStatus::TidakHadir->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                $count += Attendance::insertOrIgnore($rows);
            });

        return $count;
    }

    private function attempt(
        ?Booking $booking,
        ?string $token,
        User $scanner,
        AttendanceMethod $method,
        ?string $note,
        CarbonInterface $now,
    ): CheckInResult {
        if ($booking === null) {
            $result = CheckInResult::fail(ScanResult::InvalidQr, 'QR Code tidak valid atau tidak dikenali.');
        } else {
            try {
                $result = DB::transaction(
                    fn () => $this->validateAndRecord($booking->id, $scanner, $method, $note, $now)
                );
            } catch (UniqueConstraintViolationException) {
                // Dua scan hampir bersamaan: yang kedua ditolak oleh basis data.
                $result = CheckInResult::fail(ScanResult::Duplicate, 'Member sudah check-in.', $booking);
            }
        }

        // Log ditulis di luar transaksi agar tetap tersimpan walaupun gagal.
        ScanLog::create([
            'booking_id' => $result->booking?->id ?? $booking?->id,
            'scanned_token' => $token ? Str::limit($token, 64, '') : null,
            'result' => $result->result,
            'message' => Str::limit($result->message, 250, ''),
            'scanned_by' => $scanner->id,
            'scanned_at' => $now,
        ]);

        return $result;
    }

    private function validateAndRecord(
        int $bookingId,
        User $scanner,
        AttendanceMethod $method,
        ?string $note,
        CarbonInterface $now,
    ): CheckInResult {
        // Kunci baris booking agar dua scan bersamaan tidak saling menyalip.
        $booking = Booking::with(['member', 'classSession.classType', 'classSession.trainer', 'classSession.room'])
            ->lockForUpdate()
            ->findOrFail($bookingId);
        $session = $booking->classSession;

        if (! $booking->isActive()) {
            return CheckInResult::fail(
                ScanResult::Cancelled,
                "Booking ini sudah dibatalkan ({$booking->status->label()}).",
                $booking,
            );
        }

        $attendance = Attendance::where('booking_id', $booking->id)->first();

        if ($attendance?->status === AttendanceStatus::Hadir) {
            return CheckInResult::fail(
                ScanResult::Duplicate,
                'Member sudah check-in pukul ' . $attendance->checked_in_at->format('H:i') . '.',
                $booking,
            );
        }

        if (! $session->isCheckinOpen($now)) {
            $message = $now->lessThan($session->checkinOpensAt())
                ? 'Check-in baru dibuka pukul ' . $session->checkinOpensAt()->format('H:i')
                    . ' (kelas mulai ' . $session->starts_at->format('H:i') . ').'
                : 'Kelas sudah selesai, check-in ditutup.';

            return CheckInResult::fail(ScanResult::OutsideWindow, $message, $booking);
        }

        // Jika sebelumnya ditandai tidak hadir (mis. jam sistem bergeser), baris itu diperbarui.
        $attendance ??= new Attendance(['booking_id' => $booking->id]);
        $attendance->fill([
            'status' => AttendanceStatus::Hadir,
            'checked_in_at' => $now,
            'method' => $method,
            'recorded_by' => $scanner->id,
            'note' => $note,
        ])->save();

        $booking->setRelation('attendance', $attendance);

        return CheckInResult::ok($booking, $attendance, 'Check-in berhasil pukul ' . $now->format('H:i') . '.');
    }
}
