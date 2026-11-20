<?php

namespace App\Support;

/** Pesan validasi berbahasa Indonesia untuk formulir pengaturan, pengguna, dan akun. */
class ValidationMessages
{
    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'email' => 'Format :attribute tidak valid.',
            'unique' => ':attribute sudah dipakai akun lain.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'in' => ':attribute tidak valid.',
            'regex' => 'Format :attribute tidak valid.',
            'min.numeric' => ':attribute minimal :min.',
            'max.numeric' => ':attribute maksimal :max.',
            'min.string' => ':attribute minimal :min karakter.',
            'max.string' => ':attribute maksimal :max karakter.',
            'confirmed' => 'Konfirmasi :attribute tidak cocok.',
            'different' => 'Kata sandi baru harus berbeda dari kata sandi saat ini.',
            'current_password' => 'Kata sandi saat ini salah.',
            'arrive_early_minutes.lte' => 'Imbauan tiba lebih awal tidak boleh melebihi jendela check-in, karena member akan tiba sebelum check-in dibuka.',
        ];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'name' => 'Nama',
            'email' => 'Email',
            'password' => 'Kata sandi',
            'current_password' => 'Kata sandi saat ini',
            'role' => 'Peran',
            'studio_name' => 'Nama studio',
            'studio_address' => 'Alamat',
            'studio_phone' => 'Telepon',
            'checkin_open_minutes' => 'Jendela check-in',
            'arrive_early_minutes' => 'Imbauan tiba lebih awal',
            'cancel_limit_hours' => 'Batas pembatalan',
            'template' => 'Template pesan',
        ];
    }
}
