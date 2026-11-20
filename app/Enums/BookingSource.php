<?php

namespace App\Enums;

enum BookingSource: string
{
    case App = 'app';
    case WhatsApp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::App => 'Aplikasi',
            self::WhatsApp => 'WhatsApp',
        };
    }
}
