<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
    case LateCancelled = 'late_cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Cancelled => 'Dibatalkan',
            self::LateCancelled => 'Batal Terlambat',
        };
    }

    /** Warna badge untuk UI: success | warning | danger | neutral */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Cancelled => 'neutral',
            self::LateCancelled => 'danger',
        };
    }

    public function isCancelled(): bool
    {
        return $this !== self::Active;
    }
}
