<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\QrWhatsAppController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureRole;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Admin dan resepsionis
    Route::get('/akun', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/akun', [AccountController::class, 'updateProfile'])->name('account.update');
    Route::put('/akun/kata-sandi', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/scan-check-in', [ScanController::class, 'index'])->name('scan.index');
    Route::post('/scan-check-in/scan', [ScanController::class, 'scan'])->middleware('throttle:60,1')->name('scan.scan');
    Route::post('/scan-check-in/booking/{booking}', [ScanController::class, 'manual'])->name('scan.manual');

    // Hanya admin
    Route::middleware(EnsureRole::class . ':admin')->group(function () {
        // Data Booking (rute statis harus sebelum {booking})
        Route::get('/data-booking', [BookingController::class, 'index'])->name('bookings.index');
        Route::get('/data-booking/tambah', [BookingController::class, 'create'])->name('bookings.create');
        Route::post('/data-booking', [BookingController::class, 'store'])->name('bookings.store');

        Route::get('/data-booking/impor', [BookingImportController::class, 'create'])->name('imports.create');
        Route::get('/data-booking/impor/template', [BookingImportController::class, 'template'])->name('imports.template');
        Route::post('/data-booking/impor', [BookingImportController::class, 'store'])->name('imports.store');
        Route::get('/data-booking/impor/{batch}', [BookingImportController::class, 'show'])->name('imports.show');
        Route::post('/data-booking/impor/{batch}/konfirmasi', [BookingImportController::class, 'confirm'])->name('imports.confirm');
        Route::delete('/data-booking/impor/{batch}', [BookingImportController::class, 'destroy'])->name('imports.destroy');

        Route::get('/data-booking/{booking}', [BookingController::class, 'show'])->name('bookings.show');
        Route::post('/data-booking/{booking}/batal', [BookingController::class, 'cancel'])->name('bookings.cancel');

        // QR Check-In & WhatsApp
        Route::get('/qr-whatsapp', [QrWhatsAppController::class, 'index'])->name('qr.index');
        Route::get('/qr-whatsapp/{booking}', [QrWhatsAppController::class, 'show'])->name('qr.show');
        Route::get('/qr-whatsapp/{booking}/qr.svg', [QrWhatsAppController::class, 'image'])->name('qr.image');
        Route::post('/qr-whatsapp/{booking}/terkirim', [QrWhatsAppController::class, 'markSent'])->name('qr.sent');
        Route::post('/qr-whatsapp/{booking}/buat-ulang', [QrWhatsAppController::class, 'regenerate'])->name('qr.regenerate');

        // Laporan Absensi
        Route::get('/laporan-absensi', [AttendanceReportController::class, 'index'])->name('reports.index');
        Route::get('/laporan-absensi/ekspor/{format}', [AttendanceReportController::class, 'export'])->name('reports.export');

        // Pengaturan
        Route::get('/pengaturan', [SettingsController::class, 'edit'])->name('settings.index');
        Route::put('/pengaturan/umum', [SettingsController::class, 'updateGeneral'])->name('settings.general');
        Route::put('/pengaturan/pesan', [SettingsController::class, 'updateTemplate'])->name('settings.template');
        Route::post('/pengaturan/pesan/reset', [SettingsController::class, 'resetTemplate'])->name('settings.template.reset');
        Route::post('/pengaturan/pesan/pratinjau', [SettingsController::class, 'previewTemplate'])->name('settings.template.preview');

        // Kelola pengguna
        Route::get('/pengaturan/pengguna', [UserController::class, 'index'])->name('users.index');
        Route::post('/pengaturan/pengguna', [UserController::class, 'store'])->name('users.store');
        Route::put('/pengaturan/pengguna/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/pengaturan/pengguna/{user}/kata-sandi', [UserController::class, 'resetPassword'])->name('users.password');
    });
});
