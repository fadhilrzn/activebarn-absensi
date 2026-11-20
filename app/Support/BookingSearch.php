<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Pencarian booking berdasarkan kode booking, nama member, atau nomor WhatsApp.
 * Nomor yang diketik "0812..." dicocokkan ke format tersimpan "62812...".
 */
class BookingSearch
{
    public static function apply(Builder $query, string $term): Builder
    {
        $like = '%' . addcslashes($term, '%_\\') . '%';

        $digits = preg_replace('/\D/', '', $term) ?? '';
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }

        return $query->where(function (Builder $w) use ($like, $digits) {
            $w->where('booking_code', 'like', $like)
                ->orWhereHas('member', function (Builder $m) use ($like, $digits) {
                    $m->where('name', 'like', $like);
                    if ($digits !== '') {
                        $m->orWhere('phone', 'like', '%' . $digits . '%');
                    }
                });
        });
    }
}
