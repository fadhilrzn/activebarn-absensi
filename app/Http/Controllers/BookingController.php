<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Room;
use App\Models\Trainer;
use App\Services\BookingCancellationService;
use App\Services\BookingImportService;
use App\Support\BookingSearch;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BookingController
{
    private const FORM_FIELDS = [
        'session_number', 'date', 'start', 'end', 'class', 'trainer', 'room',
        'name', 'phone', 'source', 'package', 'price', 'payment',
    ];

    public function index(Request $request): View
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'class_type' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(BookingStatus::class)],
            'attendance' => ['nullable', 'in:hadir,tidak_hadir,belum'],
        ]);

        $bookings = Booking::query()
            ->with(['member', 'classSession.classType', 'classSession.trainer', 'classSession.room', 'attendance'])
            ->when($f['q'] ?? null, fn ($query, $q) => BookingSearch::apply($query, $q))
            ->when($f['date'] ?? null, fn ($query, $date) => $query->forDate($date))
            ->when($f['class_type'] ?? null, fn ($query, $id) => $query->whereHas('classSession', fn ($s) => $s->where('class_type_id', $id)))
            ->when($f['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($f['attendance'] ?? null, function ($query, $attendance) {
                if ($attendance === 'belum') {
                    $query->active()->whereDoesntHave('attendance');
                } else {
                    $query->whereHas('attendance', fn ($a) => $a->where('status', $attendance));
                }
            })
            ->orderByDesc(ClassSession::select('starts_at')->whereColumn('class_sessions.id', 'bookings.class_session_id'))
            ->orderByDesc('bookings.id')
            ->paginate(15)
            ->withQueryString();

        return view('bookings.index', [
            'bookings' => $bookings,
            'classTypes' => ClassType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('bookings.create', [
            'classNames' => ClassType::orderBy('name')->pluck('name'),
            'trainerNames' => Trainer::orderBy('name')->pluck('name'),
            'roomNames' => Room::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request, BookingImportService $service): RedirectResponse
    {
        $request->validate(array_fill_keys(self::FORM_FIELDS, ['nullable', 'string', 'max:200']));

        $row = $service->analyze([['row' => 1, 'raw' => $request->only(self::FORM_FIELDS)]])[0];

        if ($row['status'] !== BookingImportService::VALID) {
            return back()->withInput()->withErrors(['form' => $row['message']]);
        }

        $booking = $service->commit([$row], $request->user())->first();

        return redirect()->route('bookings.show', $booking)->with('status', 'Booking berhasil ditambahkan.');
    }

    public function show(Booking $booking): View
    {
        $booking->load(['member', 'classSession.classType', 'classSession.trainer', 'classSession.room', 'attendance', 'creator', 'qrSender']);

        return view('bookings.show', [
            'booking' => $booking,
            'canCancel' => $booking->isActive() && $booking->attendance?->status !== AttendanceStatus::Hadir,
            'defaultNotifiedAt' => now()->format('Y-m-d\TH:i'),
        ]);
    }

    public function cancel(Request $request, Booking $booking, BookingCancellationService $service): RedirectResponse
    {
        $data = $request->validate([
            'notified_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $service->cancel($booking, Carbon::parse($data['notified_at']), $data['note'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['cancel' => $e->getMessage()]);
        }

        return redirect()->route('bookings.show', $booking)
            ->with('status', 'Booking dibatalkan (' . $booking->fresh()->status->label() . ').');
    }
}
