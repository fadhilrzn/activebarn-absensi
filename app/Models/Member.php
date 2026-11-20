<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    protected $fillable = ['name', 'phone', 'notes'];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** Nomor selalu disimpan dalam format 62xxxxxxxxxx. */
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => static::normalizePhone($value),
        );
    }

    /**
     * 0812-3456-7890 / +62 812 3456 7890 / 812345678 -> 628123456789
     */
    public static function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }
        if (str_starts_with($digits, '8')) {
            return '62' . $digits;
        }

        return $digits;
    }

    /** Tautan WhatsApp dengan teks terisi; staf menekan kirim sendiri. */
    public function whatsappLink(string $message): string
    {
        return 'https://wa.me/' . $this->phone . '?text=' . rawurlencode($message);
    }
}
