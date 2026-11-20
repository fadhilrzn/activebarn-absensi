<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log setiap percobaan scan (berhasil maupun gagal) untuk audit dan
 * panel "aktivitas terakhir" di dashboard.
 *
 * result : success | invalid_qr | cancelled | outside_window | duplicate
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scanned_token', 64)->nullable();
            $table->string('result', 30)->index();
            $table->string('message')->nullable();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scanned_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_logs');
    }
};
