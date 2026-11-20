<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Member dikenali lewat nomor WhatsApp (format 62xxxxxxxxxx), bukan nama,
 * karena rekap lama hanya berisi nama depan sehingga peserta sama nama
 * tidak bisa dibedakan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->index();
            $table->string('phone', 20)->unique();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
