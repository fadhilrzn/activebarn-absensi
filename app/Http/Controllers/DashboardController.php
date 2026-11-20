<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\ScanLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController
{
    public function __invoke(Request $request): View
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = Carbon::parse($request->input('date', today()->toDateString()))->startOfDay();
        $day = $date->toDateString();

        $base = fn () => Booking::forDate($day)->active();

        $totalBooking = $base()->count();
        $hadir = $base()->whereHas('attendance', fn ($q) => $q->where('status', AttendanceStatus::Hadir))->count();
        $qrTerkirim = $base()->whereNotNull('qr_sent_at')->count();

        $sessions = ClassSession::with(['classType', 'trainer', 'room'])
            ->withCount([
                'bookings as active_bookings_count' => fn ($q) => $q->active(),
                'bookings as checked_in_count' => fn ($q) => $q->active()
                    ->whereHas('attendance', fn ($a) => $a->where('status', AttendanceStatus::Hadir)),
            ])
            ->onDate($day)
            ->orderBy('starts_at')
            ->get();

        $activities = ScanLog::with(['booking.member', 'booking.classSession.classType'])
            ->latest('scanned_at')
            ->limit(6)
            ->get();

        $hour = now()->hour;
        $greeting = match (true) {
            $hour < 11 => 'Selamat pagi',
            $hour < 15 => 'Selamat siang',
            $hour < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };

        return view('dashboard', [
            'date' => $date,
            'greeting' => $greeting,
            'totalBooking' => $totalBooking,
            'hadir' => $hadir,
            'belumHadir' => max($totalBooking - $hadir, 0),
            'qrTerkirim' => $qrTerkirim,
            'qrBelum' => max($totalBooking - $qrTerkirim, 0),
            'sessions' => $sessions,
            'activities' => $activities,
        ]);
    }
}
