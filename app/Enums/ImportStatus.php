<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Preview = 'preview';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Preview => 'Pratinjau',
            self::Confirmed => 'Dikonfirmasi',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
