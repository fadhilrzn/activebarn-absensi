<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Services\QrImageService;
use App\Services\WhatsAppMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class QrWhatsAppController
{
    public function index(Request $request): View
    {
        $f = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'class_type' => ['nullable', 'integer'],
            'qr' => ['nullable', 'in:belum,terkirim'],
        ]);
        $day = $f['date'] ?? today()->toDateString();

        $base = fn () => Booking::query()->active()->forDate($day);

        $bookings = $base()
            ->with(['member', 'classSession.classType', 'classSession.trainer', 'classSession.room', 'qrSender'])
            ->when($f['class_type'] ?? null, fn ($q, $id) => $q->whereHas('classSession', fn ($s) => $s->where('class_type_id', $id)))
            ->when(($f['qr'] ?? null) === 'belum', fn ($q) => $q->whereNull('qr_sent_at'))
            ->when(($f['qr'] ?? null) === 'terkirim', fn ($q) => $q->whereNotNull('qr_sent_at'))
            ->orderBy($this->sessionStart())
            ->orderBy('bookings.id')
            ->paginate(50)
            ->withQueryString();

        return view('qr.index', [
            'day' => $day,
            'bookings' => $bookings,
            'classTypes' => ClassType::orderBy('name')->get(['id', 'name']),
            'total' => $base()->count(),
            'sent' => $base()->whereNotNull('qr_sent_at')->count(),
            'firstPending' => $this->dayQueue($day)->first(fn (Booking $b) => $this->isPending($b)),
        ]);
    }

    public function show(Booking $booking, WhatsAppMessageService $whatsApp): View|RedirectResponse
    {
        if (! $booking->isActive()) {
            return redirect()->route('qr.index')
                ->withErrors(['qr' => 'Booking ini sudah dibatalkan, tidak perlu mengirim QR.']);
        }

        $booking->load(['member', 'classSession.classType', 'classSession.trainer', 'classSession.room', 'qrSender']);
        [$next, $remaining] = $this->nextAfter($booking);

        return view('qr.show', [
            'booking' => $booking,
            'message' => $whatsApp->render($booking),
            'waLink' => $whatsApp->link($booking),
            'ended' => $booking->classSession->ends_at->isPast(),
            'checkedIn' => $booking->attendance()->where('status', AttendanceStatus::Hadir)->exists(),
            'next' => $next,
            'remaining' => $remaining,
        ]);
    }

    public function image(Booking $booking, QrImageService $qr): Response
    {
        abort_unless($booking->isActive(), 404);

        return response($qr->svg($booking->qr_token), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Dipanggil setelah staf mengirim QR di WhatsApp (sistem tidak bisa mengetahuinya sendiri). */
    public function markSent(Request $request, Booking $booking): RedirectResponse
    {
        $booking->loadMissing(['member', 'classSession']);

        if (! $booking->isActive()) {
            return back()->withErrors(['qr' => 'Booking ini sudah dibatalkan.']);
        }
        if ($booking->classSession->ends_at->isPast()) {
            return back()->withErrors(['qr' => 'Kelas sudah selesai, QR tidak perlu dikirim.']);
        }

        $booking->update(['qr_sent_at' => now(), 'qr_sent_by' => $request->user()->id]);

        $message = "QR untuk {$booking->member->name} ditandai terkirim.";
        [$next] = $this->nextAfter($booking);

        if ($next) {
            return redirect()->route('qr.show', $next)->with('status', $message);
        }

        return redirect()
            ->route('qr.index', ['date' => $booking->classSession->starts_at->toDateString()])
            ->with('status', $message . ' Semua QR pada tanggal ini sudah terkirim.');
    }

    /** Mengganti token: QR lama langsung tidak berlaku (mis. QR bocor atau diteruskan). */
    public function regenerate(Booking $booking): RedirectResponse
    {
        if (! $booking->isActive()) {
            return back()->withErrors(['qr' => 'Booking ini sudah dibatalkan.']);
        }
        if ($booking->attendance()->where('status', AttendanceStatus::Hadir)->exists()) {
            return back()->withErrors(['qr' => 'Member sudah check-in, QR tidak perlu dibuat ulang.']);
        }

        $booking->update(['qr_token' => Str::random(40), 'qr_sent_at' => null, 'qr_sent_by' => null]);

        return redirect()->route('qr.show', $booking)
            ->with('status', 'QR baru dibuat. QR lama tidak berlaku lagi; kirim ulang QR ini ke member.');
    }

    // -------------------------------------------------------------- antrean

    private function sessionStart()
    {
        return ClassSession::select('starts_at')->whereColumn('class_sessions.id', 'bookings.class_session_id');
    }

    /** Semua booking aktif pada satu tanggal, urut jam sesi lalu nomor booking. */
    private function dayQueue(string $day): Collection
    {
        return Booking::query()->active()->forDate($day)
            ->with('classSession')
            ->orderBy($this->sessionStart())
            ->orderBy('bookings.id')
            ->get();
    }

    /** Belum dikirim DAN kelasnya belum selesai. */
    private function isPending(Booking $booking): bool
    {
        return $booking->qr_sent_at === null && $booking->classSession->ends_at->isFuture();
    }

    /**
     * @return array{0:?Booking, 1:int} booking berikutnya yang belum dikirim, dan jumlah yang tersisa
     */
    private function nextAfter(Booking $current): array
    {
        $rows = $this->dayQueue($current->classSession->starts_at->toDateString());
        $position = $rows->search(fn (Booking $b) => $b->id === $current->id);

        $next = $rows->slice($position === false ? 0 : $position + 1)->first(fn (Booking $b) => $this->isPending($b))
            ?? $rows->first(fn (Booking $b) => $b->id !== $current->id && $this->isPending($b));

        return [$next, $rows->filter(fn (Booking $b) => $this->isPending($b))->count()];
    }
}
