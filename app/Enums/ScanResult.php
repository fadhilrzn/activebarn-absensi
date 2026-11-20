<?php

namespace App\Enums;

enum ScanResult: string
{
    case Success = 'success';
    case InvalidQr = 'invalid_qr';
    case Cancelled = 'cancelled';
    case OutsideWindow = 'outside_window';
    case Duplicate = 'duplicate';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Check-in berhasil',
            self::InvalidQr => 'QR tidak valid',
            self::Cancelled => 'Booking sudah dibatalkan',
            self::OutsideWindow => 'Di luar jadwal check-in',
            self::Duplicate => 'Sudah check-in sebelumnya',
        };
    }

    public function color(): string
    {
        return $this === self::Success ? 'success' : 'danger';
    }
}
