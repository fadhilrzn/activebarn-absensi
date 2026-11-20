<?php

namespace App\Console\Commands;

use App\Services\CheckInService;
use Illuminate\Console\Command;

class MarkAbsentCommand extends Command
{
    protected $signature = 'attendance:mark-absent';

    protected $description = 'Tandai tidak hadir untuk booking aktif pada sesi yang sudah selesai';

    public function handle(CheckInService $service): int
    {
        $count = $service->markAbsent();

        $this->info("{$count} booking ditandai tidak hadir.");

        return self::SUCCESS;
    }
}
