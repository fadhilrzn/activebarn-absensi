<?php

namespace App\Models;

use App\Enums\ScanResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanLog extends Model
{
    /** Tabel hanya punya kolom scanned_at, tanpa created_at/updated_at. */
    public $timestamps = false;

    protected $fillable = [
        'booking_id', 'scanned_token', 'result', 'message', 'scanned_by', 'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => ScanResult::class,
            'scanned_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
