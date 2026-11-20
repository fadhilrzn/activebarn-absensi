<?php

namespace App\Services;

use App\Enums\ScanResult;
use App\Models\Attendance;
use App\Models\Booking;

/**
 * Hasil satu percobaan check-in. Dipakai controller untuk menampilkan
 * kartu konfirmasi (berhasil) atau pesan kesalahan (gagal).
 */
final class CheckInResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ScanResult $result,
        public readonly string $message,
        public readonly ?Booking $booking = null,
        public readonly ?Attendance $attendance = null,
    ) {
    }

    public static function ok(Booking $booking, Attendance $attendance, string $message): self
    {
        return new self(true, ScanResult::Success, $message, $booking, $attendance);
    }

    public static function fail(ScanResult $result, string $message, ?Booking $booking = null): self
    {
        return new self(false, $result, $message, $booking);
    }
}
