<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\WhatsAppMessageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Data awal. Password di bawah HANYA untuk pengembangan, ganti sebelum deploy.
 */
class InitialDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach (['Move Room', 'Beyond Room', 'Today Room', 'Active Room'] as $name) {
            DB::table('rooms')->updateOrInsert(['name' => $name], ['updated_at' => $now, 'created_at' => $now]);
        }

        foreach (['Hot Pilates', 'Reformer Pilates', 'Muaythai', 'Zumba', 'Hyrox'] as $name) {
            DB::table('class_types')->updateOrInsert(['name' => $name], ['updated_at' => $now, 'created_at' => $now]);
        }

        $settings = [
            'checkin_open_minutes'  => '30',  // check-in dibuka 30 menit sebelum kelas
            'cancel_limit_hours'    => '3',   // batas batal/reschedule 3 jam sebelum kelas
            'arrive_early_minutes'  => '10',  // imbauan tiba lebih awal (dipakai di pesan WhatsApp)
            'studio_name'           => 'Active Barn',
            'whatsapp_template'     => WhatsAppMessageService::DEFAULT_TEMPLATE,
        ];
        foreach ($settings as $key => $value) {
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => $now, 'created_at' => $now]);
        }

        User::updateOrCreate(['email' => 'admin@activebarn.test'], [
            'name' => 'Admin', 'password' => Hash::make('password'), 'role' => 'admin', 'is_active' => true,
        ]);
        User::updateOrCreate(['email' => 'resepsionis@activebarn.test'], [
            'name' => 'Resepsionis', 'password' => Hash::make('password'), 'role' => 'resepsionis', 'is_active' => true,
        ]);
    }
}
