<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Resepsionis = 'resepsionis';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Resepsionis => 'Resepsionis',
        };
    }
}
