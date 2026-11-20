<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Setting;
use Carbon\CarbonInterface;
use DomainException;

/**
 * Membatalkan booking dan menentukan statusnya:
 *  - cancelled      : member memberi kabar paling lambat N jam sebelum kelas
 *  - late_cancelled : member memberi kabar kurang dari N jam sebelum kelas
 * N diambil dari settings.cancel_limit_hours (default 3).
 *
 * $notifiedAt = waktu member memberi kabar (dari chat WhatsApp), diinput staf.
 */
class BookingCancellationService
{
    public function cancel(Booking $booking, ?CarbonInterface $notifiedAt = null, ?string $note = null): Booking
    {
        $notifiedAt ??= now();

        if (! $booking->isActive()) {
            throw new DomainException('Booking ini sudah dibatalkan.');
        }

        if ($booking->attendance()->where('status', AttendanceStatus::Hadir)->exists()) {
            throw new DomainException('Member sudah check-in, booking tidak bisa dibatalkan.');
        }

        $deadline = $booking->classSession->starts_at
            ->copy()
            ->subHours(Setting::int('cancel_limit_hours', 3));

        $booking->update([
            'status' => $notifiedAt->greaterThan($deadline)
                ? BookingStatus::LateCancelled
                : BookingStatus::Cancelled,
            'cancel_notified_at' => $notifiedAt,
            'cancel_note' => $note,
        ]);

        return $booking;
    }
}
