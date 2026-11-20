<?php

namespace App\Http\Controllers;

use App\Enums\ScanResult;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Setting;
use App\Services\CheckInResult;
use App\Services\CheckInService;
use App\Support\BookingSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScanController
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $openMinutes = Setting::int('checkin_open_minutes', 30);

        $bookings = Booking::query()
            ->active()
            ->forDate(today()->toDateString())
            ->with(['member', 'classSession.classType', 'classSession.trainer', 'classSession.room', 'attendance'])
            ->when($q !== '', fn ($query) => BookingSearch::apply($query, $q))
            // Tanpa kata kunci: tampilkan hanya sesi yang check-in-nya sedang dibuka.
            ->when($q === '', fn ($query) => $query->whereHas('classSession', fn ($s) => $s
                ->where('starts_at', '<=', now()->addMinutes($openMinutes))
                ->where('ends_at', '>=', now())))
            ->orderBy(ClassSession::select('starts_at')->whereColumn('class_sessions.id', 'bookings.class_session_id'))
            ->orderBy('bookings.id')
            ->limit(30)
            ->get();

        return view('scan.index', [
            'q' => $q,
            'bookings' => $bookings,
            'openMinutes' => $openMinutes,
            'result' => session('checkin'),
        ]);
    }

    /** Dipanggil oleh pemindai kamera (JSON). Semua aturan divalidasi di server. */
    public function scan(Request $request, CheckInService $service): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:255']]);

        return response()->json($this->payload($service->scan($data['token'], $request->user())));
    }

    /** Check-in manual dari daftar pencarian (cadangan jika QR tidak terbaca). */
    public function manual(Request $request, Booking $booking, CheckInService $service): RedirectResponse
    {
        $result = $service->manual($booking, $request->user(), 'Check-in manual dari halaman scan');

        $q = trim((string) $request->input('q', ''));

        return redirect()
            ->route('scan.index', $q !== '' ? ['q' => $q] : [])
            ->with('checkin', $this->payload($result));
    }

    /** @return array<string, mixed> */
    private function payload(CheckInResult $result): array
    {
        $booking = $result->booking;
        $session = $booking?->classSession;

        return [
            'success' => $result->success,
            'result' => $result->result->value,
            'title' => $this->titleFor($result->result),
            'message' => $result->message,
            'booking' => $booking ? [
                'member' => $booking->member->name,
                'class' => $session->classType->name,
                'trainer' => $session->trainer->name,
                'room' => $session->room->name,
                'code' => $booking->booking_code,
                'schedule' => $session->schedule_label . ' – ' . $session->ends_at->format('H:i'),
            ] : null,
        ];
    }

    private function titleFor(ScanResult $result): string
    {
        return match ($result) {
            ScanResult::Success => 'Check-In Berhasil',
            ScanResult::InvalidQr => 'QR Tidak Valid',
            ScanResult::Cancelled => 'Booking Dibatalkan',
            ScanResult::OutsideWindow => 'Di Luar Jadwal Check-In',
            ScanResult::Duplicate => 'Sudah Check-In',
        };
    }
}
