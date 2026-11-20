<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan kehadiran, maksimal satu per booking (booking_id unik = tidak ada
 * check-in ganda, dijamin oleh basis data).
 *
 * status : hadir | tidak_hadir
 *          tidak_hadir dibuat otomatis (scheduler) setelah sesi selesai
 *          untuk booking aktif yang tidak dipindai; recorded_by = null.
 * method : scan | manual (null jika tidak_hadir)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 20)->index();
            $table->dateTime('checked_in_at')->nullable();
            $table->string('method', 20)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('checked_in_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
