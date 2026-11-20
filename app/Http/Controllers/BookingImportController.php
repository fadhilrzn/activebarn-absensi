<?php

namespace App\Http\Controllers;

use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use App\Services\BookingImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingImportController
{
    private const PREVIEW_LIMIT = 500;

    public function create(): View
    {
        return view('imports.create', [
            'recent' => ImportBatch::with('importer')->latest()->limit(5)->get(),
        ]);
    }

    public function template(BookingImportService $service): StreamedResponse
    {
        return response()->streamDownload(function () use ($service) {
            (new Xlsx($service->templateSpreadsheet()))->save('php://output');
        }, 'template-impor-booking.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function store(Request $request, BookingImportService $service): RedirectResponse
    {
        $request->validate(
            ['file' => ['required', 'file', 'extensions:xlsx', 'max:5120']],
            [
                'file.required' => 'Pilih file Excel (.xlsx) terlebih dahulu.',
                'file.extensions' => 'File harus berformat .xlsx.',
                'file.max' => 'Ukuran file maksimal 5 MB.',
            ],
        );

        $file = $request->file('file');
        $batch = ImportBatch::create([
            'file_name' => $file->getClientOriginalName(),
            'status' => ImportStatus::Preview,
            'imported_by' => $request->user()->id,
        ]);

        $path = "imports/{$batch->id}.xlsx";
        Storage::disk('local')->putFileAs('imports', $file, "{$batch->id}.xlsx");

        try {
            $rows = $service->analyze($service->parseFile(Storage::disk('local')->path($path)));
        } catch (RuntimeException $e) {
            Storage::disk('local')->delete($path);
            $batch->delete();

            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $this->saveSummary($batch, $service, $rows);

        return redirect()->route('imports.show', $batch);
    }

    public function show(ImportBatch $batch, BookingImportService $service): View
    {
        $rows = [];
        $summary = [
            'total' => $batch->total_rows,
            'valid' => $batch->valid_rows,
            'duplicate' => $batch->duplicate_rows,
            'invalid' => $batch->invalid_rows,
        ];
        $error = null;

        if ($batch->status === ImportStatus::Preview) {
            try {
                $rows = $service->analyze($service->parseFile($this->path($batch)));
                $summary = $service->summarize($rows);
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }

        return view('imports.show', [
            'batch' => $batch,
            'rows' => array_slice($rows, 0, self::PREVIEW_LIMIT),
            'truncated' => count($rows) > self::PREVIEW_LIMIT,
            'summary' => $summary,
            'error' => $error,
        ]);
    }

    public function confirm(Request $request, ImportBatch $batch, BookingImportService $service): RedirectResponse
    {
        abort_unless($batch->status === ImportStatus::Preview, 404);

        try {
            $rows = $service->analyze($service->parseFile($this->path($batch)));
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $summary = $service->summarize($rows);
        if ($summary['valid'] === 0) {
            return back()->withErrors(['file' => 'Tidak ada baris valid yang dapat diimpor.']);
        }

        $created = $service->commit($rows, $request->user(), $batch);

        $this->saveSummary($batch, $service, $rows);
        $batch->update(['status' => ImportStatus::Confirmed, 'confirmed_at' => now()]);

        return redirect()->route('bookings.index')
            ->with('status', "{$created->count()} booking berhasil diimpor dari {$batch->file_name}.");
    }

    public function destroy(ImportBatch $batch): RedirectResponse
    {
        abort_unless($batch->status === ImportStatus::Preview, 404);

        $batch->update(['status' => ImportStatus::Cancelled]);
        Storage::disk('local')->delete("imports/{$batch->id}.xlsx");

        return redirect()->route('imports.create')->with('status', 'Impor dibatalkan. Tidak ada data yang disimpan.');
    }

    private function path(ImportBatch $batch): string
    {
        $relative = "imports/{$batch->id}.xlsx";

        if (! Storage::disk('local')->exists($relative)) {
            throw new RuntimeException('File impor tidak ditemukan. Unggah ulang file Excel.');
        }

        return Storage::disk('local')->path($relative);
    }

    private function saveSummary(ImportBatch $batch, BookingImportService $service, array $rows): void
    {
        $summary = $service->summarize($rows);

        $problems = collect($rows)
            ->where('status', '!=', BookingImportService::VALID)
            ->take(200)
            ->map(fn ($r) => ['row' => $r['row'], 'status' => $r['status'], 'message' => $r['message']])
            ->values()
            ->all();

        $batch->update([
            'total_rows' => $summary['total'],
            'valid_rows' => $summary['valid'],
            'duplicate_rows' => $summary['duplicate'],
            'invalid_rows' => $summary['invalid'],
            'error_report' => $problems,
        ]);
    }
}
