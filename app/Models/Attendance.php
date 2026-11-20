<?php

namespace App\Models;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'booking_id', 'status', 'checked_in_at', 'method', 'recorded_by', 'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'method' => AttendanceMethod::class,
            'checked_in_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
