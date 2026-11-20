<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'file_name', 'total_rows', 'valid_rows', 'duplicate_rows', 'invalid_rows',
        'status', 'error_report', 'imported_by', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'error_report' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
