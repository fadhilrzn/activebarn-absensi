<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Jenis kelas: Hot Pilates, Reformer Pilates, Muaythai, Zumba, Hyrox, dst. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_types');
    }
};
