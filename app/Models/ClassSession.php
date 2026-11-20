<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassSession extends Model
{
    protected $fillable = [
        'session_number', 'class_type_id', 'trainer_id', 'room_id', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function classType(): BelongsTo
    {
        return $this->belongsTo(ClassType::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeOnDate(Builder $query, CarbonInterface|string $date): Builder
    {
        return $query->whereDate('starts_at', $date);
    }

    /** Sesi yang sudah selesai pada waktu tertentu (dipakai penanda "tidak hadir"). */
    public function scopeEndedBefore(Builder $query, CarbonInterface $at): Builder
    {
        return $query->where('ends_at', '<=', $at);
    }

    /** Check-in dibuka N menit sebelum kelas (default 30, dari tabel settings). */
    public function checkinOpensAt(): CarbonInterface
    {
        return $this->starts_at->copy()->subMinutes(Setting::int('checkin_open_minutes', 30));
    }

    public function checkinClosesAt(): CarbonInterface
    {
        return $this->ends_at;
    }

    public function isCheckinOpen(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $at->greaterThanOrEqualTo($this->checkinOpensAt())
            && $at->lessThanOrEqualTo($this->checkinClosesAt());
    }

    /** Contoh: "Senin, 05 Oktober 2026 | 09:30". Butuh APP_LOCALE=id. */
    public function getScheduleLabelAttribute(): string
    {
        return $this->starts_at->translatedFormat('l, d F Y') . ' | ' . $this->starts_at->format('H:i');
    }
}
