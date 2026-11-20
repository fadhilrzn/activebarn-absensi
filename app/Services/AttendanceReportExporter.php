<?php

namespace App\Services;

use App\Enums\AttendanceMethod;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\User;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor laporan ke Excel (.xlsx) dan CSV dengan kolom seperti rekap "Absen Class".
 * Semua teks ditulis sebagai teks (bukan rumus), dan CSV mengamankan sel yang diawali = + - @.
 */
class AttendanceReportExporter
{
    public const HEADERS = [
        'No', 'Nomor Sesi', 'Tanggal', 'Jam Mulai', 'Jam Selesai', 'Kelas', 'Trainer', 'Ruangan',
        'Nama Member', 'No. WhatsApp', 'Sumber', 'Paket', 'Harga', 'Metode Pembayaran',
        'Status Booking', 'Status Kehadiran', 'Waktu Check-In', 'Metode Check-In',
    ];

    public function __construct(private AttendanceReport $report)
    {
    }

    public function filename(array $f, string $extension): string
    {
        return "laporan-absensi_{$f['from']}_{$f['to']}.{$extension}";
    }

    /** @return array<string, mixed> */
    private function line(object $row, int $no): array
    {
        $start = Carbon::parse($row->starts_at);
        $state = AttendanceReport::stateKey($row->booking_status, $row->att_status, $row->att_id !== null, $row->ends_at);

        return [
            'no' => $no,
            'sesi' => (int) $row->session_number,
            'tanggal' => $start,
            'mulai' => $start->format('H:i'),
            'selesai' => Carbon::parse($row->ends_at)->format('H:i'),
            'kelas' => (string) $row->class_name,
            'trainer' => (string) $row->trainer_name,
            'ruangan' => (string) $row->room_name,
            'nama' => (string) $row->member_name,
            'phone' => (string) $row->phone,
            'sumber' => BookingSource::tryFrom((string) $row->source)?->label() ?? (string) $row->source,
            'paket' => (string) ($row->package_type ?? ''),
            'harga' => (int) $row->price,
            'bayar' => (string) ($row->payment_method ?? ''),
            'status_booking' => BookingStatus::tryFrom((string) $row->booking_status)?->label() ?? (string) $row->booking_status,
            'status_hadir' => AttendanceReport::STATES[$state][0],
            'checkin' => $row->checked_in_at ? Carbon::parse($row->checked_in_at) : null,
            'metode' => $row->att_method ? (AttendanceMethod::tryFrom((string) $row->att_method)?->label() ?? '') : '',
        ];
    }

    public function xlsx(array $f, User $user): StreamedResponse
    {
        return response()->streamDownload(function () use ($f, $user) {
            $book = new Spreadsheet();
            $sheet = $book->getActiveSheet();
            $sheet->setTitle('Absensi');

            foreach (self::HEADERS as $i => $label) {
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($i + 1) . '1', $label, DataType::TYPE_STRING);
            }

            $text = fn (string $col, int $r, string $v) => $sheet->setCellValueExplicit("{$col}{$r}", $v, DataType::TYPE_STRING);
            $r = 2;
            foreach ($this->report->exportRows($f) as $row) {
                $x = $this->line($row, $r - 1);
                $sheet->setCellValue("A{$r}", $x['no']);
                $sheet->setCellValue("B{$r}", $x['sesi']);
                $sheet->setCellValue("C{$r}", ExcelDate::PHPToExcel($x['tanggal']->copy()->startOfDay()));
                $text('D', $r, $x['mulai']);
                $text('E', $r, $x['selesai']);
                $text('F', $r, $x['kelas']);
                $text('G', $r, $x['trainer']);
                $text('H', $r, $x['ruangan']);
                $text('I', $r, $x['nama']);
                $text('J', $r, $x['phone']);
                $text('K', $r, $x['sumber']);
                $text('L', $r, $x['paket']);
                $sheet->setCellValue("M{$r}", $x['harga']);
                $text('N', $r, $x['bayar']);
                $text('O', $r, $x['status_booking']);
                $text('P', $r, $x['status_hadir']);
                if ($x['checkin']) {
                    $sheet->setCellValue("Q{$r}", ExcelDate::PHPToExcel($x['checkin']));
                }
                $text('R', $r, $x['metode']);
                $r++;
            }
            $last = max($r - 1, 2);

            $sheet->getStyle("C2:C{$last}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            $sheet->getStyle("M2:M{$last}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("Q2:Q{$last}")->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');
            $sheet->getStyle('A1:R1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A1:R1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F2937');
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:R{$last}");
            foreach (range(1, count(self::HEADERS)) as $i) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
            }

            $this->summarySheet($book, $f, $user);
            $book->setActiveSheetIndex(0);

            (new Xlsx($book))->save('php://output');
        }, $this->filename($f, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function csv(array $f): StreamedResponse
    {
        return response()->streamDownload(function () use ($f) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, self::HEADERS, ';', '"', '');

            $no = 1;
            foreach ($this->report->exportRows($f) as $row) {
                $x = $this->line($row, $no++);
                fputcsv($out, array_map([$this, 'safe'], [
                    $x['no'], $x['sesi'], $x['tanggal']->format('d/m/Y'), $x['mulai'], $x['selesai'],
                    $x['kelas'], $x['trainer'], $x['ruangan'], $x['nama'], $x['phone'], $x['sumber'],
                    $x['paket'], $x['harga'], $x['bayar'], $x['status_booking'], $x['status_hadir'],
                    $x['checkin']?->format('d/m/Y H:i') ?? '', $x['metode'],
                ]), ';', '"', '');
            }
            fclose($out);
        }, $this->filename($f, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Cegah injeksi rumus: sel teks yang diawali = + - @ diberi awalan apostrof. */
    private function safe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }

    private function summarySheet(Spreadsheet $book, array $f, User $user): void
    {
        $s = $this->report->summary($f);
        $sheet = $book->createSheet();
        $sheet->setTitle('Ringkasan');

        $status = $f['status'] ? (AttendanceReport::STATES[$f['status']][0] ?? $f['status']) : 'Semua';
        $rows = [
            ['Laporan Absensi Active Barn', ''],
            ['Periode', Carbon::parse($f['from'])->format('d/m/Y') . ' sampai ' . Carbon::parse($f['to'])->format('d/m/Y')],
            ['Filter status', $status],
            ['Dibuat pada', now()->format('d/m/Y H:i')],
            ['Dibuat oleh', $user->name],
            ['', ''],
            ['Booking aktif', $s['aktif']],
            ['Hadir', $s['hadir']],
            ['Tidak hadir', $s['tidak_hadir']],
            ['Belum (sesi belum selesai)', $s['belum']],
            ['Batal', $s['batal']],
            ['Batal terlambat', $s['batal_terlambat']],
            ['Tingkat kehadiran', $s['tingkat'] === null ? '-' : number_format($s['tingkat'], 1, ',', '.') . '%'],
            ['', ''],
            ['Catatan', 'Tingkat kehadiran = hadir / (hadir + tidak hadir). Booking batal dan sesi yang belum selesai tidak dihitung.'],
        ];
        foreach ($rows as $i => [$label, $value]) {
            $r = $i + 1;
            $sheet->setCellValueExplicit("A{$r}", (string) $label, DataType::TYPE_STRING);
            is_int($value)
                ? $sheet->setCellValue("B{$r}", $value)
                : $sheet->setCellValueExplicit("B{$r}", (string) $value, DataType::TYPE_STRING);
        }
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(60);
    }
}
