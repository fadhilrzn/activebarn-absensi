<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * QR dibuat sebagai SVG di server (tanpa ekstensi Imagick/GD).
 * Browser yang mengubahnya menjadi PNG saat staf menekan Unduh atau Salin.
 */
class QrImageService
{
    public function svg(string $token): string
    {
        return (string) QrCode::format('svg')
            ->size(320)
            ->margin(2)
            ->errorCorrection('M')
            ->generate($token);
    }
}
