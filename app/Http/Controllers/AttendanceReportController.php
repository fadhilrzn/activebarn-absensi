<?php

namespace App\Http\Controllers;

use App\Models\ClassType;
use App\Models\Trainer;
use App\Services\AttendanceReport;
use App\Services\AttendanceReportExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportController
{
    public function index(Request $request, AttendanceReport $report): View
    {
        $f = $this->filters($request);

        return view('reports.index', [
            'f' => $f,
            'summary' => $report->summary($f),
            'groups' => $report->groups($f, $f['group']),
            'bookings' => $report->tableQuery($f)
                ->with(['member', 'classSession.classType', 'classSession.trainer', 'classSession.room', 'attendance'])
                ->paginate(25)
                ->withQueryString(),
            'classTypes' => ClassType::orderBy('name')->get(['id', 'name']),
            'trainers' => Trainer::orderBy('name')->get(['id', 'name']),
            'states' => AttendanceReport::STATES,
            'now' => now(),
        ]);
    }

    public function export(Request $request, string $format, AttendanceReportExporter $exporter): StreamedResponse
    {
        abort_unless(in_array($format, ['xlsx', 'csv'], true), 404);

        $f = $this->filters($request);

        return $format === 'xlsx' ? $exporter->xlsx($f, $request->user()) : $exporter->csv($f);
    }

    /** @return array{from:string,to:string,class_type:?int,trainer:?int,status:?string,sort:string,dir:string,group:string} */
    private function filters(Request $request): array
    {
        $toRules = ['nullable', 'date_format:Y-m-d'];
        if ($request->filled('from')) {
            $toRules[] = 'after_or_equal:from';
        }

        $v = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => $toRules,
            'class_type' => ['nullable', 'integer'],
            'trainer' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(array_keys(AttendanceReport::STATES))],
            'sort' => ['nullable', Rule::in(array_keys(AttendanceReport::SORTS))],
            'dir' => ['nullable', 'in:asc,desc'],
            'group' => ['nullable', 'in:kelas,trainer,member'],
        ]);

        [$defaultFrom, $defaultTo] = AttendanceReport::defaultRange();
        $from = $v['from'] ?? $defaultFrom;
        $to = $v['to'] ?? $defaultTo;
        if ($to < $from) {
            $to = Carbon::parse($from)->endOfMonth()->toDateString();
        }

        return [
            'from' => $from,
            'to' => $to,
            'class_type' => isset($v['class_type']) ? (int) $v['class_type'] : null,
            'trainer' => isset($v['trainer']) ? (int) $v['trainer'] : null,
            'status' => $v['status'] ?? null,
            'sort' => $v['sort'] ?? 'jadwal',
            'dir' => $v['dir'] ?? 'desc',
            'group' => $v['group'] ?? 'kelas',
        ];
    }
}
