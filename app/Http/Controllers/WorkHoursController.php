<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WorkHoursController extends Controller
{
    public function index(Request $request)
    {
        // --- фильтры ---
        $dateFrom = $request->query('date_from') ?: Carbon::now()->startOfMonth()->toDateString();
        $dateTo   = $request->query('date_to')   ?: Carbon::now()->endOfMonth()->toDateString();

        $from = Carbon::parse($dateFrom)->startOfDay();
        $to   = Carbon::parse($dateTo)->endOfDay();

        $employeeId = $request->query('employee_id'); // optional
        $department = $request->query('department');  // optional
        $position   = $request->query('position');    // optional
        $doorId     = $request->query('door_id');     // optional

        // буфер по краям, чтобы корректно резать смены на границах периода
        $qFrom = (clone $from)->subDay();
        $qTo   = (clone $to)->addDay();

        // --- дедуп: одинаковые события -> оставляем MIN(IdPassage) ---
        $dedup = DB::table('passage')
            ->selectRaw('MIN(IdPassage) as IdPassage')
            ->whereBetween('AccessTime', [$qFrom, $qTo])
            ->where('AccessMethod', 'Карта')
            ->whereNotNull('IdMergedVisitControl')
            ->groupBy('IdMergedVisitControl', 'AccessTime', 'AccessType', 'DoorID');

        // --- события + человек ---
        $q = DB::table('passage as p')
            ->joinSub($dedup, 'd', function ($join) {
                $join->on('p.IdPassage', '=', 'd.IdPassage');
            })
            ->join('mergedvisitcontrol as m', 'm.idmergedvisitcontrol', '=', 'p.IdMergedVisitControl')
            ->select([
                'p.IdMergedVisitControl',
                'p.AccessTime',
                'p.AccessType',
                'p.DoorID',
                'm.LastName',
                'm.FirstName',
                'm.MiddleName',
                'm.Department',
                'm.Position',
            ])
            ->orderBy('p.IdMergedVisitControl')
            ->orderBy('p.AccessTime');

        if (!empty($employeeId)) $q->where('p.IdMergedVisitControl', (int)$employeeId);
        if (!empty($department)) $q->where('m.Department', $department);
        if (!empty($position))   $q->where('m.Position', $position);
        if (!empty($doorId))     $q->where('p.DoorID', $doorId);

        $events = $q->get();

        $rows = $this->calcWorkedHours($events, $from, $to);

        return view('workhours.index', [
            'rows' => $rows,
            'filters' => [
                'date_from'   => $dateFrom,
                'date_to'     => $dateTo,
                'employee_id' => $employeeId,
                'department'  => $department,
                'position'    => $position,
                'door_id'     => $doorId,
            ],
        ]);
    }

    private function calcWorkedHours($events, Carbon $periodFrom, Carbon $periodTo): array
    {
        // группируем по сотруднику
        $grouped = [];
        foreach ($events as $e) {
            $id = (int)$e->IdMergedVisitControl;

            if (!isset($grouped[$id])) {
                $fio = trim(($e->LastName ?? '') . ' ' . ($e->FirstName ?? '') . ' ' . ($e->MiddleName ?? ''));
                $grouped[$id] = [
                    'employee_id' => $id,
                    'fio'         => $fio,
                    'department'  => $e->Department ?? '',
                    'position'    => $e->Position ?? '',
                    'events'      => [],
                ];
            }

            $grouped[$id]['events'][] = $e;
        }

        // настройки
        $minShiftMinutes = (int)config('worktime.min_shift_minutes', 30);
        $maxSegmentHours = (int)config('worktime.max_segment_hours', 20);
        $maxShiftHours   = (int)config('worktime.max_shift_hours', 18);
        $shiftBreakHours = (int)config('worktime.shift_break_hours', 3);
        $dupResetMinutes = (int)config('worktime.dup_in_reset_minutes', 15);

        $minShiftSec   = max(0, $minShiftMinutes) * 60;
        $maxSegSec     = max(1, $maxSegmentHours) * 3600;
        $maxShiftSec   = max(1, $maxShiftHours) * 3600;
        $shiftBreakSec = max(1, $shiftBreakHours) * 3600;
        $dupResetSec   = max(0, $dupResetMinutes) * 60;

        $periodFromTs = $periodFrom->getTimestamp();
        $periodToTs   = $periodTo->getTimestamp();

        $result = [];

        foreach ($grouped as $id => $data) {
            $eventsList = $data['events'];
            usort($eventsList, fn($a, $b) => strcmp((string)$a->AccessTime, (string)$b->AccessTime));

            // 1) лента -> сегменты IN->OUT
            $segments = [];
            $openInTs = null;

            foreach ($eventsList as $e) {
                $type = mb_strtolower(trim((string)$e->AccessType), 'UTF-8');
                if ($type !== 'вход' && $type !== 'выход') continue;
                $ts = strtotime($e->AccessTime);
                if (!$ts) continue;

                if ($type === 'вход') {
                    if ($openInTs === null) $openInTs = $ts;
                    else if ($dupResetSec > 0 && ($ts - $openInTs) >= $dupResetSec) $openInTs = $ts;
                    continue;
                }

                // выход
                if ($openInTs === null) continue;
                $dur = $ts - $openInTs;
                if ($dur > 0 && $dur <= $maxSegSec) $segments[] = [$openInTs, $ts];
                $openInTs = null;
            }

            if (!$segments) {
                $result[] = [
                    'employee_id'  => $data['employee_id'],
                    'fio'          => $data['fio'],
                    'department'   => $data['department'],
                    'position'     => $data['position'],
                    'worked_hours' => 0.00,
                ];
                continue;
            }

            // 2) клип к периоду
            $clipped = [];
            foreach ($segments as [$inTs, $outTs]) {
                if ($outTs <= $periodFromTs || $inTs >= $periodToTs) continue;
                $start = max($inTs, $periodFromTs);
                $end   = min($outTs, $periodToTs);
                if ($end > $start) $clipped[] = [$start, $end];
            }

            if (!$clipped) {
                $result[] = [
                    'employee_id'  => $data['employee_id'],
                    'fio'          => $data['fio'],
                    'department'   => $data['department'],
                    'position'     => $data['position'],
                    'worked_hours' => 0.00,
                ];
                continue;
            }

            usort($clipped, fn($a, $b) => $a[0] <=> $b[0]);

            // 3) склейка в смены
            $workedSeconds = 0;
            $shiftSum = 0;
            $prevOut = null;

            foreach ($clipped as [$s, $e]) {
                if ($prevOut !== null) {
                    $gap = $s - $prevOut;
                    if ($gap > $shiftBreakSec) {
                        if ($shiftSum >= $minShiftSec && $shiftSum <= $maxShiftSec) {
                            $workedSeconds += $shiftSum;
                        }
                        $shiftSum = 0;
                    }
                }

                $dur = $e - $s;
                if ($dur > 0) $shiftSum += $dur;
                $prevOut = $e;
            }

            if ($shiftSum >= $minShiftSec && $shiftSum <= $maxShiftSec) {
                $workedSeconds += $shiftSum;
            }

            $result[] = [
                'employee_id'  => $data['employee_id'],
                'fio'          => $data['fio'],
                'department'   => $data['department'],
                'position'     => $data['position'],
                'worked_hours' => round($workedSeconds / 3600, 2),
            ];
        }

        usort($result, fn($a, $b) => $b['worked_hours'] <=> $a['worked_hours']);
        return $result;
    }
}
