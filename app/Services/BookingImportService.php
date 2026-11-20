<?php

namespace App\Services;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\ImportBatch;
use App\Models\Member;
use App\Models\Room;
use App\Models\Trainer;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use RuntimeException;
use Throwable;

/**
 * Membaca, memvalidasi, dan menyimpan booking dari file Excel yang disusun
 * manual. Form input manual memakai alur yang sama (satu baris).
 *
 * Satu baris Excel = satu booking. Sesi, trainer, kelas, ruangan, dan member
 * dibuat otomatis bila belum ada.
 */
class BookingImportService
{
    public const VALID = 'valid';
    public const DUPLICATE = 'duplicate';
    public const INVALID = 'invalid';

    /** kunci internal => nama kolom di Excel (judul alternatif ikut dikenali) */
    private const ALIASES = [
        'session_number' => ['Nomor Sesi', 'No Sesi', 'Sesi'],
        'date' => ['Tanggal'],
        'start' => ['Jam Mulai'],
        'end' => ['Jam Selesai'],
        'class' => ['Kelas'],
        'trainer' => ['Trainer', 'Instruktur'],
        'room' => ['Ruangan', 'Studio'],
        'name' => ['Nama Member', 'Nama'],
        'phone' => ['No. WhatsApp', 'No WhatsApp', 'No WA', 'Nomor WhatsApp', 'WhatsApp'],
        'source' => ['Sumber'],
        'package' => ['Paket', 'Jenis Paket'],
        'price' => ['Harga'],
        'payment' => ['Metode Pembayaran', 'Metode Bayar'],
    ];

    private const REQUIRED = ['session_number', 'date', 'start', 'end', 'class', 'trainer', 'room', 'name', 'phone'];

    public const TEMPLATE_HEADERS = [
        'Nomor Sesi', 'Tanggal', 'Jam Mulai', 'Jam Selesai', 'Kelas', 'Trainer', 'Ruangan',
        'Nama Member', 'No. WhatsApp', 'Sumber', 'Paket', 'Harga', 'Metode Pembayaran',
    ];

    // ------------------------------------------------------------------ BACA FILE

    /**
     * @return list<array{row:int, raw:array<string,mixed>}>
     */
    public function parseFile(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($path)->getSheet(0);
            $values = $sheet->toArray(null, true, false, false);
        } catch (Throwable $e) {
            throw new RuntimeException('File tidak dapat dibaca sebagai Excel (.xlsx).', 0, $e);
        }

        if ($values === []) {
            throw new RuntimeException('File kosong.');
        }

        $columnOf = $this->mapHeader(array_shift($values));
        $missing = array_diff(self::REQUIRED, array_keys($columnOf));
        if ($missing !== []) {
            $labels = array_map(fn ($key) => self::ALIASES[$key][0], $missing);
            throw new RuntimeException('Kolom wajib tidak ditemukan: ' . implode(', ', $labels) . '.');
        }

        $items = [];
        foreach ($values as $index => $cells) {
            $raw = [];
            foreach ($columnOf as $key => $col) {
                $raw[$key] = $cells[$col] ?? null;
            }
            if ($this->isBlankRow($raw)) {
                continue;
            }
            $items[] = ['row' => $index + 2, 'raw' => $raw]; // +2: header di baris 1
        }

        return $items;
    }

    /** @return array<string,int> kunci => indeks kolom */
    private function mapHeader(array $header): array
    {
        $lookup = [];
        foreach (self::ALIASES as $key => $names) {
            foreach ($names as $name) {
                $lookup[$this->slug($name)] = $key;
            }
        }

        $map = [];
        foreach ($header as $col => $title) {
            $key = $lookup[$this->slug((string) $title)] ?? null;
            if ($key !== null && ! isset($map[$key])) {
                $map[$key] = $col;
            }
        }

        return $map;
    }

    private function slug(string $text): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim($text))) ?? '';
    }

    private function isBlankRow(array $raw): bool
    {
        foreach ($raw as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    // ------------------------------------------------------------ NORMALISASI

    /**
     * @return array{0:?array<string,mixed>, 1:?string} [data, pesan kesalahan]
     */
    public function normalizeRow(array $raw): array
    {
        $errors = [];
        $d = [];

        $number = $this->parseInt($raw['session_number'] ?? null);
        if ($number === null || $number < 1) {
            $errors[] = 'Nomor Sesi harus bilangan bulat positif';
        } else {
            $d['session_number'] = $number;
        }

        $date = $this->parseDate($raw['date'] ?? null);
        if ($date === null) {
            $errors[] = 'Tanggal tidak valid (contoh: 05/10/2026)';
        } else {
            $d['date'] = $date;
        }

        $start = $this->parseTime($raw['start'] ?? null);
        if ($start === null) {
            $errors[] = 'Jam Mulai tidak valid (contoh: 09:30)';
        } else {
            $d['start'] = $start;
        }

        $end = $this->parseTime($raw['end'] ?? null);
        if ($end === null) {
            $errors[] = 'Jam Selesai tidak valid (contoh: 10:30)';
        } else {
            $d['end'] = $end;
        }

        if ($start !== null && $end !== null && $end <= $start) {
            $errors[] = 'Jam Selesai harus setelah Jam Mulai';
        }

        foreach ([['class', 'Kelas', 80], ['trainer', 'Trainer', 100], ['room', 'Ruangan', 50], ['name', 'Nama Member', 100]] as [$key, $label, $max]) {
            $value = $this->clean($raw[$key] ?? null);
            if ($value === '') {
                $errors[] = "{$label} wajib diisi";
            } elseif (mb_strlen($value) > $max) {
                $errors[] = "{$label} maksimal {$max} karakter";
            } else {
                $d[$key] = $value;
            }
        }

        foreach ([['package', 'Paket', 60], ['payment', 'Metode Pembayaran', 30]] as [$key, $label, $max]) {
            $value = $this->clean($raw[$key] ?? null);
            if (mb_strlen($value) > $max) {
                $errors[] = "{$label} maksimal {$max} karakter";
            } else {
                $d[$key] = $value === '' ? null : $value;
            }
        }

        $phone = $this->parsePhone($raw['phone'] ?? null);
        if ($phone === null) {
            $errors[] = 'No. WhatsApp tidak valid (contoh: 0812-3456-7890)';
        } else {
            $d['phone'] = $phone;
        }

        $source = $this->parseSource($raw['source'] ?? null);
        if ($source === null) {
            $errors[] = 'Sumber harus "Aplikasi" atau "WhatsApp"';
        } else {
            $d['source'] = $source;
        }

        $price = $this->parsePrice($raw['price'] ?? null);
        if ($price === null) {
            $errors[] = 'Harga harus berupa angka (contoh: 150000)';
        } else {
            $d['price'] = $price;
        }

        return $errors === [] ? [$d, null] : [null, implode('; ', $errors)];
    }

    private function clean(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }

    private function parseInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }
        if (is_string($value) && preg_match('/^\s*\d+\s*$/', $value)) {
            return (int) $value;
        }

        return null;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_numeric($value) && (float) $value > 59) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }

        $text = trim((string) $value);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $text, $m)) {
            [$y, $mo, $da] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2}|\d{4})$/', $text, $m)) {
            [$da, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            if ($y < 100) {
                $y += 2000;
            }
        } else {
            return null;
        }

        return checkdate($mo, $da, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $da) : null;
    }

    private function parseTime(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i');
        }
        if (is_numeric($value) && ! is_string($value)) {
            $number = (float) $value;
            if ($number < 0 || ($number >= 1 && $number < 1000)) {
                return null;
            }
            $minutes = ((int) round(fmod($number, 1) * 1440)) % 1440;

            return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        if (preg_match('/^(\d{1,2})[:.](\d{2})(?::\d{2})?$/', trim((string) $value), $m)) {
            if ((int) $m[1] < 24 && (int) $m[2] < 60) {
                return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
            }
        }

        return null;
    }

    private function parsePhone(mixed $value): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        $text = is_string($value) ? $value : number_format((float) $value, 0, '', '');
        $phone = Member::normalizePhone($text);

        return preg_match('/^62\d{8,13}$/', $phone) ? $phone : null;
    }

    private function parseSource(mixed $value): ?BookingSource
    {
        $text = $this->slug($this->clean($value));

        return match ($text) {
            '', 'whatsapp', 'wa' => BookingSource::WhatsApp,
            'aplikasi', 'app' => BookingSource::App,
            default => null,
        };
    }

    private function parsePrice(mixed $value): ?int
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return 0;
        }
        if (is_int($value) || is_float($value)) {
            return $value < 0 ? null : (int) round($value);
        }

        $digits = preg_replace('/(rp|\s|\.|,)/i', '', (string) $value);

        return ($digits !== '' && ctype_digit($digits)) ? (int) $digits : null;
    }

    // --------------------------------------------------------------- ANALISIS

    /**
     * Menilai tiap baris: valid, duplikat, atau tidak valid.
     * Termasuk mendeteksi nomor sesi yang dipakai untuk dua sesi berbeda
     * dan dua sesi di ruangan yang sama pada jam yang sama.
     *
     * @param  list<array{row:int, raw:array<string,mixed>}>  $items
     * @return list<array{row:int, raw:array, status:string, message:?string, data:?array}>
     */
    public function analyze(array $items): array
    {
        $sessionDefs = [];  // nomor sesi => ['sig' => ..., 'label' => ...]
        $slotOwners = [];   // "ruangan|tanggal jam" => nomor sesi
        $seenPairs = [];    // "phone|nomor" => baris pertama
        $result = [];

        foreach ($items as $item) {
            [$data, $error] = $this->normalizeRow($item['raw']);

            if ($data === null) {
                $result[] = $this->resultRow($item, self::INVALID, $error, null);
                continue;
            }

            $number = $data['session_number'];
            $sig = $this->signature($data);
            $label = $this->label($data);
            $existing = ClassSession::with(['classType', 'trainer', 'room'])->where('session_number', $number)->first();

            // 1. Nomor sesi konsisten dalam file
            if (isset($sessionDefs[$number]) && $sessionDefs[$number]['sig'] !== $sig) {
                $result[] = $this->resultRow($item, self::INVALID,
                    "Nomor sesi {$number} sudah dipakai untuk sesi lain di file ini ({$sessionDefs[$number]['label']})", $data);
                continue;
            }

            // 2. Nomor sesi konsisten dengan data yang sudah tersimpan
            if ($existing) {
                $stored = $this->signatureFromSession($existing);
                if ($stored['sig'] !== $sig) {
                    $result[] = $this->resultRow($item, self::INVALID,
                        "Nomor sesi {$number} sudah tersimpan sebagai sesi lain ({$stored['label']})", $data);
                    continue;
                }
            }

            // 3. Dua sesi tidak boleh di ruangan dan jam yang sama
            $slotKey = mb_strtolower($data['room']) . '|' . $data['date'] . ' ' . $data['start'];
            $slotTaken = (isset($slotOwners[$slotKey]) && $slotOwners[$slotKey] !== $number)
                || ClassSession::where('starts_at', $data['date'] . ' ' . $data['start'] . ':00')
                    ->where('session_number', '!=', $number)
                    ->whereHas('room', fn ($q) => $q->whereRaw('LOWER(name) = ?', [mb_strtolower($data['room'])]))
                    ->exists();
            if ($slotTaken) {
                $result[] = $this->resultRow($item, self::INVALID,
                    "{$data['room']} sudah dipakai sesi lain pada {$data['date']} pukul {$data['start']}", $data);
                continue;
            }

            $sessionDefs[$number] ??= ['sig' => $sig, 'label' => $label];
            $slotOwners[$slotKey] ??= $number;

            // 4. Duplikat booking
            $pair = $data['phone'] . '|' . $number;
            if (isset($seenPairs[$pair])) {
                $result[] = $this->resultRow($item, self::DUPLICATE, "Duplikat dengan baris {$seenPairs[$pair]} di file ini", $data);
                continue;
            }
            $seenPairs[$pair] = $item['row'];

            $existsInDb = $existing && Booking::where('class_session_id', $existing->id)
                ->whereHas('member', fn ($q) => $q->where('phone', $data['phone']))
                ->exists();
            if ($existsInDb) {
                $result[] = $this->resultRow($item, self::DUPLICATE, 'Booking ini sudah ada di sistem', $data);
                continue;
            }

            $result[] = $this->resultRow($item, self::VALID, null, $data);
        }

        return $result;
    }

    /** @return array{total:int, valid:int, duplicate:int, invalid:int} */
    public function summarize(array $analyzed): array
    {
        $counts = array_count_values(array_column($analyzed, 'status'));

        return [
            'total' => count($analyzed),
            'valid' => $counts[self::VALID] ?? 0,
            'duplicate' => $counts[self::DUPLICATE] ?? 0,
            'invalid' => $counts[self::INVALID] ?? 0,
        ];
    }

    private function resultRow(array $item, string $status, ?string $message, ?array $data): array
    {
        return ['row' => $item['row'], 'raw' => $item['raw'], 'status' => $status, 'message' => $message, 'data' => $data];
    }

    private function signature(array $d): string
    {
        return mb_strtolower(implode('|', [$d['date'], $d['start'], $d['end'], $d['class'], $d['trainer'], $d['room']]));
    }

    private function label(array $d): string
    {
        return "{$d['class']}, {$d['trainer']}, {$d['room']}, {$d['date']} {$d['start']}-{$d['end']}";
    }

    /** @return array{sig:string, label:string} */
    private function signatureFromSession(ClassSession $s): array
    {
        $d = [
            'date' => $s->starts_at->format('Y-m-d'),
            'start' => $s->starts_at->format('H:i'),
            'end' => $s->ends_at->format('H:i'),
            'class' => $s->classType->name,
            'trainer' => $s->trainer->name,
            'room' => $s->room->name,
        ];

        return ['sig' => $this->signature($d), 'label' => $this->label($d)];
    }

    // ------------------------------------------------------------------ SIMPAN

    /**
     * Menyimpan hanya baris berstatus valid, dalam satu transaksi.
     *
     * @return Collection<int, Booking> booking yang dibuat
     */
    public function commit(array $analyzed, User $user, ?ImportBatch $batch = null): Collection
    {
        return DB::transaction(function () use ($analyzed, $user, $batch) {
            $created = new Collection();

            foreach ($analyzed as $row) {
                if ($row['status'] !== self::VALID) {
                    continue;
                }
                $d = $row['data'];

                $session = ClassSession::where('session_number', $d['session_number'])->first()
                    ?? ClassSession::create([
                        'session_number' => $d['session_number'],
                        'class_type_id' => $this->findOrCreate(ClassType::class, $d['class'])->id,
                        'trainer_id' => $this->findOrCreate(Trainer::class, $d['trainer'])->id,
                        'room_id' => $this->findOrCreate(Room::class, $d['room'])->id,
                        'starts_at' => $d['date'] . ' ' . $d['start'] . ':00',
                        'ends_at' => $d['date'] . ' ' . $d['end'] . ':00',
                    ]);

                $member = Member::where('phone', $d['phone'])->first()
                    ?? Member::create(['name' => $d['name'], 'phone' => $d['phone']]);

                $created->push(Booking::create([
                    'member_id' => $member->id,
                    'class_session_id' => $session->id,
                    'source' => $d['source'],
                    'package_type' => $d['package'],
                    'price' => $d['price'],
                    'payment_method' => $d['payment'],
                    'status' => BookingStatus::Active,
                    'import_batch_id' => $batch?->id,
                    'created_by' => $user->id,
                ]));
            }

            return $created;
        });
    }

    /** @param class-string<ClassType|Trainer|Room> $model */
    private function findOrCreate(string $model, string $name): ClassType|Trainer|Room
    {
        return $model::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
            ?? $model::create(['name' => $name]);
    }

    // ---------------------------------------------------------------- TEMPLATE

    public function templateSpreadsheet(): Spreadsheet
    {
        $book = new Spreadsheet();

        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Booking');
        $sheet->fromArray(self::TEMPLATE_HEADERS, null, 'A1');
        $sheet->getStyle('A1:M1')->getFont()->setBold(true);
        $sheet->getStyle('B2:B1000')->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sheet->getStyle('C2:D1000')->getNumberFormat()->setFormatCode('hh:mm');
        $sheet->getStyle('I2:I1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $help = $book->createSheet();
        $help->setTitle('Petunjuk');
        $lines = [
            ['PETUNJUK PENGISIAN (sheet "Booking" yang diimpor; sheet ini tidak dibaca)'],
            [''],
            ['Satu baris = satu booking. Jangan mengubah nama kolom pada baris pertama.'],
            ['Nomor Sesi', 'Bilangan bulat unik per sesi. Satu nomor hanya boleh untuk satu sesi.', '1204'],
            ['Tanggal', 'dd/mm/yyyy atau yyyy-mm-dd', '05/10/2026'],
            ['Jam Mulai / Jam Selesai', 'Format jam:menit', '09:30 / 10:30'],
            ['Kelas, Trainer, Ruangan', 'Wajib. Penulisan yang sama dianggap data yang sama.', 'Hot Pilates / Amanda K. / Move Room'],
            ['Nama Member', 'Wajib', 'Siti Aminah'],
            ['No. WhatsApp', 'Wajib. Kolom ini dikenali sebagai kunci member.', '0812-3456-7890'],
            ['Sumber', 'Aplikasi atau WhatsApp (kosong = WhatsApp)', 'WhatsApp'],
            ['Paket, Harga, Metode Pembayaran', 'Opsional. Harga angka saja, tanpa titik.', 'Paket 5x / 750000 / Transfer'],
        ];
        $help->fromArray($lines, null, 'A1');
        $help->getStyle('A1')->getFont()->setBold(true);
        foreach (['A', 'B', 'C'] as $col) {
            $help->getColumnDimension($col)->setAutoSize(true);
        }

        $book->setActiveSheetIndex(0);

        return $book;
    }
}
