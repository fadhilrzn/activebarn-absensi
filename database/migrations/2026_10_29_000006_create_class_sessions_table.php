<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sesi kelas yang diinput admin (tidak diambil dari CMS).
 * session_number unik agar nomor sesi tidak bisa terpakai dua kali.
 * Jendela check-in dihitung di aplikasi dari starts_at/ends_at dan tabel settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('session_number')->unique();
            $table->foreignId('class_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('trainer_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();

            $table->unique(['room_id', 'starts_at']);
            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
