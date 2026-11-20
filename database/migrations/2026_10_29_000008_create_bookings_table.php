<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris = satu booking member pada satu sesi.
 *
 * status         : active | cancelled | late_cancelled
 *                  (batal vs batal terlambat ditentukan aplikasi dengan
 *                  membandingkan cancel_notified_at dan starts_at sesi
 *                  terhadap batas jam di tabel settings).
 * source         : app | whatsapp
 * qr_token       : token acak yang dikodekan ke QR (bukan booking_code),
 *                  supaya tidak mudah ditebak.
 * price, package_type, payment_method: dibutuhkan rekap fee trainer & pendapatan
 *                  (harga dalam rupiah, bilangan bulat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code', 20)->unique();
            $table->string('qr_token', 64)->unique();

            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_session_id')->constrained()->restrictOnDelete();

            $table->string('source', 20);
            $table->string('package_type', 60)->nullable();
            $table->unsignedInteger('price')->default(0);
            $table->string('payment_method', 30)->nullable();

            $table->string('status', 20)->default('active');
            $table->dateTime('cancel_notified_at')->nullable();
            $table->string('cancel_note')->nullable();

            $table->timestamp('qr_sent_at')->nullable();
            $table->foreignId('qr_sent_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // Satu member tidak boleh punya dua booking pada sesi yang sama.
            $table->unique(['member_id', 'class_session_id']);
            $table->index(['class_session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
