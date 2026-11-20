<?php

namespace App\Enums;

enum AttendanceMethod: string
{
    case Scan = 'scan';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Scan => 'Scan QR',
            self::Manual => 'Manual',
        };
    }
}
