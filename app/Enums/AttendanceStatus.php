<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Hadir = 'hadir';
    case TidakHadir = 'tidak_hadir';

    public function label(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::TidakHadir => 'Tidak Hadir',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Hadir => 'success',
            self::TidakHadir => 'danger',
        };
    }
}
