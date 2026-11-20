<?php

namespace App\Models;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Booking extends Model
{
    protected $fillable = [
        'booking_code', 'qr_token', 'member_id', 'class_session_id', 'source',
        'package_type', 'price', 'payment_method', 'status',
        'cancel_notified_at', 'cancel_note', 'qr_sent_at', 'qr_sent_by',
        'import_batch_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'source' => BookingSource::class,
            'status' => BookingStatus::class,
            'price' => 'integer',
            'cancel_notified_at' => 'datetime',
            'qr_sent_at' => 'datetime',
        ];
    }

    /** Kode booking dan token QR dibuat otomatis jika belum diisi. */
    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            if (empty($booking->qr_token)) {
                $booking->qr_token = Str::random(40);
            }
            if (empty($booking->booking_code)) {
                $booking->booking_code = static::generateCode();
            }
        });
    }

    /** Contoh: AB-2610-K7M2Q (tanpa huruf/angka yang mirip: O, I, 0, 1). */
    public static function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $suffix = '';
            for ($i = 0; $i < 5; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = 'AB-' . now()->format('ym') . '-' . $suffix;
        } while (static::where('booking_code', $code)->exists());

        return $code;
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function attendance(): HasOne
    {
        return $this->hasOne(Attendance::class);
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function qrSender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qr_sent_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', BookingStatus::Active);
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereHas('classSession', fn (Builder $q) => $q->whereDate('starts_at', $date));
    }

    public function isActive(): bool
    {
        return $this->status === BookingStatus::Active;
    }
}
