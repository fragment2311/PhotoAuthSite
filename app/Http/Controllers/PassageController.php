<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Collection\CellsFactory;
use PhpOffice\PhpSpreadsheet\CachedObjectStorage\CacheBase;
use PhpOffice\PhpSpreadsheet\CachedObjectStorageFactory;
use App\Services\LicenseService;
use Illuminate\Support\Collection;


class PassageController extends Controller
{
    public function index(Request $request)
    {
        $applied = (string)$request->query('applied', '0') === '1';
        $params = $this->resolveReportParams($request);

        $from = $params['from'];
        $to = $params['to'];
        $q = $params['q'];
        $department = $params['department'];
        $position = $params['position'];
        $breakMinutes = $params['breakMinutes'];
        $periodStart = $params['periodStart'];
        $periodEnd = $params['periodEnd'];
        $qFrom = $params['qFrom'];
        $qTo = $params['qTo'];

        $sort = (string)$request->query('sort', 'fio');
        $dir  = strtolower((string)$request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $allowedSort = [
            'fio'          => 'fio',
            'department'   => 'department',
            'position'     => 'position',
            'worked_hours' => 'worked_hours',
        ];
        if (!isset($allowedSort[$sort])) {
            $sort = 'fio';
        }

        $filters = [
            'from'          => $from,
            'to'            => $to,
            'q'             => $q,
            'department'    => $department,
            'position'      => $position,
            'break_minutes' => $breakMinutes,
            'sort'          => $sort,
            'dir'           => $dir,
            'applied'       => $applied ? '1' : '0',
        ];

        /** @var LicenseService $license */
        $license = app(LicenseService::class);
        $reportAccess = $license->reportAccess();

        $canViewReport = (bool)$reportAccess['allowed_view'];
        $canDownload   = (bool)$reportAccess['allowed_download'];
        $licenseBanner = $reportAccess['banner'] ?? ['show' => false];

        if (!$canViewReport) {
            $rows = new LengthAwarePaginator([], 0, 50, 1, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);

            return view('passage.index', [
                'rows' => $rows,
                'filters' => $filters,
                'departments' => [],
                'positions' => [],
                'licenseBanner' => $licenseBanner,
                'canViewReport' => $canViewReport,
                'canDownloadReport' => false,
            ]);
        }

        $lookupTtl = max(30, (int) config('worktime.lookup_cache_ttl_seconds', 300));

        $departments = Cache::remember('report.lookup.departments.v1', $lookupTtl, function () {
            return DB::table('mergedvisitcontrol')
                ->whereNotNull('Department')
                ->where('Department', '<>', '')
                ->distinct()
                ->orderBy('Department')
                ->pluck('Department');
        });

        $positions = Cache::remember('report.lookup.positions.v1', $lookupTtl, function () {
            return DB::table('mergedvisitcontrol')
                ->whereNotNull('Position')
                ->where('Position', '<>', '')
                ->distinct()
                ->orderBy('Position')
                ->pluck('Position');
        });

        if (!$applied) {
            $rows = new LengthAwarePaginator([], 0, 50, 1, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);

            return view('passage.index', [
                'rows' => $rows,
                'filters' => $filters,
                'departments' => $departments,
                'positions' => $positions,
                'licenseBanner' => $licenseBanner,
                'canViewReport' => $canViewReport,
                'canDownloadReport' => false,
            ]);
        }

        $dailyRows = $this->getDailyRowsForPeriod(
            $qFrom,
            $qTo,
            $periodStart,
            $periodEnd,
            $department,
            $position,
            $q,
            $breakMinutes
        );

        $rows = array_map(function (array $row): array {
            $totalSeconds = array_sum($row['daily_seconds'] ?? []);

            return [
                'employee_id' => (int)($row['employee_id'] ?? 0),
                'fio' => (string)($row['fio'] ?? ''),
                'department' => (string)($row['department'] ?? ''),
                'position' => (string)($row['position'] ?? ''),
                'worked_hours' => round($totalSeconds / 3600, 2),
            ];
        }, $dailyRows);

        usort($rows, function (array $a, array $b) use ($sort, $dir): int {
            $ka = $a[$sort] ?? null;
            $kb = $b[$sort] ?? null;

            if ($ka == $kb) {
                return 0;
            }

            if (is_numeric($ka) && is_numeric($kb)) {
                $res = ((float)$ka <=> (float)$kb);
            } else {
                $res = strcmp((string)$ka, (string)$kb);
            }

            return $dir === 'asc' ? $res : -$res;
        });

        $page    = max(1, (int)$request->query('page', 1));
        $perPage = 50;

        $total     = count($rows);
        $rowsSlice = array_slice($rows, ($page - 1) * $perPage, $perPage);

        $paginator = new LengthAwarePaginator(
            $rowsSlice,
            $total,
            $perPage,
            $page,
            [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('passage.index', [
            'rows' => $paginator,
            'departments' => $departments,
            'positions' => $positions,
            'filters' => $filters,
            'licenseBanner' => $licenseBanner,
            'canViewReport' => $canViewReport,
            'canDownloadReport' => $canDownload && $applied,
        ]);
    }

private function calcWorkedHours($events, Carbon $periodFrom, Carbon $periodTo, int $breakMinutes = 20): array
{
    // =========================
    // 0) Группировка по сотрудникам
    // =========================
    $group = [];

    foreach ($events as $e) {
        $employeeId = (int)($e->IdMergedVisitControl ?? 0);
        if ($employeeId <= 0) continue;

        if (!isset($group[$employeeId])) {
            $fio = trim(
                ((string)($e->LastName ?? '')) . ' ' .
                ((string)($e->FirstName ?? '')) . ' ' .
                ((string)($e->MiddleName ?? ''))
            );

            $group[$employeeId] = [
                'employee_id' => $employeeId,
                'fio'         => $fio,
                'department'  => (string)($e->Department ?? ''),
                'position'    => (string)($e->Position ?? ''),
                'plant'       => $this->extractPlant((string)($e->DoorID ?? '')),
                'events'      => [],
            ];
        }

        $group[$employeeId]['events'][] = $e;
    }

    // =========================
    // 1) Настройки
    // =========================
    $minDayMinutes   = (int)config('worktime.min_shift_minutes', 30);  // минималка, чтобы смену засчитать
    $maxSegmentHours = (int)config('worktime.max_segment_hours', 20);  // защита IN->OUT
    $maxShiftHours   = (int)config('worktime.max_shift_hours', 18);    // защита суммарной смены
    $shiftBreakHours = (int)config('worktime.shift_break_hours', 3);   // разрыв OUT->IN => новая смена

    // параметр из UI: время на перерыв (мин) — в этой логике не используется
    $breakMinutes    = max(0, min(240, (int)$breakMinutes));

    // при дублях "Вход" без "Выход": если разрыв большой — считаем, что первый вход ошибочный
    $dupResetMinutes = (int)config('worktime.dup_in_reset_minutes', 15);

    $minDaySec       = max(0, $minDayMinutes) * 60;
    $maxSegSec       = max(1, $maxSegmentHours) * 3600;
    $maxShiftSec     = max(1, $maxShiftHours) * 3600;
    $shiftBreakSec   = max(1, $shiftBreakHours) * 3600;
    $breakSec        = $breakMinutes * 60;
    $dupResetSec     = max(0, $dupResetMinutes) * 60;

    $periodFromTs = $periodFrom->getTimestamp();
    $periodToTs   = $periodTo->getTimestamp();

    $result = [];

    foreach ($group as $employeeId => $d) {
        $eventsList = $d['events'];

        usort($eventsList, fn($a, $b) => strcmp((string)$a->AccessTime, (string)$b->AccessTime));

        // -------------------------
        // 2.1) Лента -> сегменты IN->OUT
        // -------------------------
        $segments = []; // [[inTs,outTs], ...]
        $openInTs = null;

        foreach ($eventsList as $ev) {
            $type = mb_strtolower(trim((string)($ev->AccessType ?? '')), 'UTF-8');
            if ($type !== 'вход' && $type !== 'выход') continue;

            try {
                $ts = Carbon::parse($ev->AccessTime)->getTimestamp();
            } catch (\Throwable $ex) {
                continue;
            }

            if ($type === 'вход') {
                if ($openInTs === null) {
                    $openInTs = $ts;
                } else {
                    // если второй "вход" пришёл сильно позже — считаем первый ошибочным
                    if ($dupResetSec > 0 && ($ts - $openInTs) >= $dupResetSec) {
                        $openInTs = $ts;
                    }
                }
                continue;
            }

            // выход
            if ($openInTs === null) continue;

            $dur = $ts - $openInTs;
            if ($dur > 0 && $dur <= $maxSegSec) {
                $segments[] = [$openInTs, $ts];
            }
            $openInTs = null;
        }

        if (!$segments) {
            $result[] = [
                'employee_id'   => (int)$employeeId,
                'fio'           => $d['fio'],
                'department'    => $d['department'],
                'position'      => $d['position'],
                'plant'         => $d['plant'] ?? '—',
                'worked_hours'  => 0.00,
                'break_count'   => 0,
                'break_minutes' => 0,
            ];
            continue;
        }

        // -------------------------
        // 2.2) Склеиваем сегменты в смены (разрыв OUT->IN > shiftBreakSec => новая смена)
        // -------------------------
        $workedSeconds = 0;
        $breakTotalSec = 0;
        $breakCount = 0;

        // клип к периоду
        $clipped = [];
        foreach ($segments as [$inTs, $outTs]) {
            if ($outTs <= $periodFromTs || $inTs >= $periodToTs) continue;
            $start = max($inTs, $periodFromTs);
            $end   = min($outTs, $periodToTs);
            if ($end > $start) $clipped[] = [$start, $end];
        }

        if (!$clipped) {
            $result[] = [
                'employee_id'   => (int)$employeeId,
                'fio'           => $d['fio'],
                'department'    => $d['department'],
                'position'      => $d['position'],
                'plant'         => $d['plant'] ?? '—',
                'worked_hours'  => 0.00,
                'break_count'   => 0,
                'break_minutes' => 0,
            ];
            continue;
        }

        usort($clipped, fn($a, $b) => $a[0] <=> $b[0]);

        $shiftSum = 0;
        $prevOut = null;

        // размах смены минус «перерывы» (паузы >= breakSec)
        $shiftStart = null;
        $shiftEnd = null;
        $breakSum = 0;

        foreach ($clipped as [$s, $e]) {
            if ($prevOut !== null) {
                $gap = $s - $prevOut;
                if ($gap > $shiftBreakSec) {
                    // закрываем прошлую смену
                    if ($shiftStart !== null && $shiftEnd !== null) {
                        $span = $shiftEnd - $shiftStart;
                        $net = $span - $breakSum;
                        if ($net < 0) $net = 0;
                        if ($net >= $minDaySec && $net <= $maxShiftSec) {
                            $workedSeconds += $net;
                        }
                        $breakTotalSec += $breakSum;
                    }
                    $shiftStart = null;
                    $shiftEnd = null;
                    $breakSum = 0;
                } else {
                    // перерыв внутри смены: если разрыв >= breakSec — вычитаем весь разрыв
                    if ($gap >= $breakSec) {
                        $breakSum += $gap;
                        $breakCount++;
                    }
                }
            }

            if ($shiftStart === null) $shiftStart = $s;
            $shiftEnd = $e;
            $prevOut = $e;
        }

        // финальная смена
        if ($shiftStart !== null && $shiftEnd !== null) {
            $span = $shiftEnd - $shiftStart;
            $net = $span - $breakSum;
            if ($net < 0) $net = 0;
            if ($net >= $minDaySec && $net <= $maxShiftSec) {
                $workedSeconds += $net;
            }
        }

        $result[] = [
            'employee_id'  => (int)$employeeId,
            'fio'          => $d['fio'],
            'department'   => $d['department'],
            'position'     => $d['position'],
            'plant'        => $d['plant'] ?? '—',
            'worked_hours' => round($workedSeconds / 3600, 2),
        ];
    }

    return $result;
}





private function resolveReportParams(Request $request): array
{
    $from = $request->query('from');
    $to   = $request->query('to');
    $q    = trim((string)$request->query('q', ''));

    $department = trim((string)$request->query('department', ''));
    $position   = trim((string)$request->query('position', ''));

    $breakMinutes = (int)$request->query('break_minutes', 20);
    $breakMinutes = max(0, min(240, $breakMinutes));

    $q = preg_replace('/[\x{00A0}\x{2007}\x{202F}]/u', ' ', $q);
    $q = preg_replace('/\s+/u', ' ', $q);
    $q = trim($q);

    if (!$from && !$to) {
        $from = now()->startOfMonth()->toDateString();
        $to   = now()->endOfMonth()->toDateString();
    }

    if ($from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = null;
    if ($to   && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = null;

    $periodStart = $from
        ? Carbon::parse($from)->startOfDay()
        : now()->startOfMonth()->startOfDay();
    $periodEnd = $to
        ? Carbon::parse($to)->endOfDay()
        : now()->endOfMonth()->endOfDay();

    if ($periodEnd->lt($periodStart)) {
        [$periodStart, $periodEnd] = [$periodEnd->copy()->startOfDay(), $periodStart->copy()->endOfDay()];
    }

    $maxReportDays = max(1, (int) config('worktime.report_max_days', 62));
    $periodDays = $periodStart->diffInDays($periodEnd) + 1;
    if ($periodDays > $maxReportDays) {
        $periodStart = $periodEnd->copy()->subDays($maxReportDays - 1)->startOfDay();
    }

    return [
        'from' => $periodStart->toDateString(),
        'to' => $periodEnd->toDateString(),
        'q' => $q,
        'department' => $department !== '' ? $department : null,
        'position' => $position !== '' ? $position : null,
        'breakMinutes' => $breakMinutes,
        'periodStart' => $periodStart,
        'periodEnd' => $periodEnd,
        'qFrom' => (clone $periodStart)->subDay(),
        'qTo' => (clone $periodEnd)->addDay(),
    ];
}

private function buildWorktimeEventsQuery(Carbon $qFrom, Carbon $qTo, ?string $department, ?string $position, string $q)
{
    $dedup = DB::table('passage')
        ->selectRaw('MIN(IdPassage) as IdPassage')
        ->whereBetween('AccessTime', [$qFrom, $qTo])
        ->whereNotNull('IdMergedVisitControl')
        ->groupBy('IdMergedVisitControl', 'AccessTime', 'AccessType', 'DoorID');

    $eventsQ = DB::table('passage as p')
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
        ->when($department, function ($qq) use ($department) {
            $qq->where('m.Department', $department);
        })
        ->when($position, function ($qq) use ($position) {
            $qq->where('m.Position', $position);
        })
        ->orderBy('p.IdMergedVisitControl')
        ->orderBy('p.AccessTime');

    if ($q !== '') {
        $isNumeric = ctype_digit($q);

        $eventsQ->where(function ($w) use ($q, $isNumeric) {
            if ($isNumeric) {
                $w->orWhere('p.IdMergedVisitControl', (int)$q);
            }

            $w->orWhere('m.LastName', 'like', "%{$q}%")
                ->orWhere('m.FirstName', 'like', "%{$q}%")
                ->orWhere('m.MiddleName', 'like', "%{$q}%")
                ->orWhere('m.Department', 'like', "%{$q}%")
                ->orWhere('m.Position', 'like', "%{$q}%")
                ->orWhere('p.DoorID', 'like', "%{$q}%");

            $w->orWhereRaw(
                "TRIM(CONCAT(COALESCE(m.LastName,''),' ',COALESCE(m.FirstName,''),' ',COALESCE(m.MiddleName,''))) LIKE ?",
                ["%{$q}%"]
            );

            $w->orWhereRaw(
                "TRIM(CONCAT(COALESCE(m.LastName,''),' ',COALESCE(m.FirstName,''))) LIKE ?",
                ["%{$q}%"]
            );
        });
    }

    return $eventsQ;
}

private function getDailyRowsForPeriod(
    Carbon $qFrom,
    Carbon $qTo,
    Carbon $periodStart,
    Carbon $periodEnd,
    ?string $department,
    ?string $position,
    string $q,
    int $breakMinutes
): array {
    $ttl = max(30, (int) config('worktime.report_cache_ttl_seconds', 120));

    $cacheKey = $this->reportCacheKey([
        'from' => $qFrom->toDateTimeString(),
        'to' => $qTo->toDateTimeString(),
        'periodStart' => $periodStart->toDateString(),
        'periodEnd' => $periodEnd->toDateString(),
        'department' => $department,
        'position' => $position,
        'q' => $q,
        'breakMinutes' => max(0, min(240, $breakMinutes)),
    ]);

    return Cache::remember($cacheKey, $ttl, function () use (
        $qFrom,
        $qTo,
        $department,
        $position,
        $q,
        $periodStart,
        $periodEnd,
        $breakMinutes
    ) {
        $events = $this->buildWorktimeEventsQuery($qFrom, $qTo, $department, $position, $q)->get();
        return $this->calcPeriodDailyRows($events, $periodStart, $periodEnd, $breakMinutes);
    });
}

private function reportCacheKey(array $payload): string
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        $json = serialize($payload);
    }

    return 'report.daily_rows.v1.' . sha1($json);
}

private function buildReportQuery(Request $request)
{
    $from = $request->query('from');
    $to   = $request->query('to');
    $q    = trim((string)$request->query('q', ''));

    if (!$from && !$to) {
        $from = now()->startOfMonth()->toDateString();
        $to   = now()->endOfMonth()->toDateString();
    }

    $allowedSort = [
        'AccessTime'   => 'p.AccessTime',
        'DoorID'       => 'p.DoorID',
        'AccessType'   => 'p.AccessType',
        'AccessMethod' => 'p.AccessMethod',
        'LastName'     => 'm.LastName',
        'FirstName'    => 'm.FirstName',
        'MiddleName'   => 'm.MiddleName',
        'Department'   => 'm.Department',
        'Position'     => 'm.Position',
        'FirstCard'    => 'm.FirstCard',
        'SecondCard'   => 'm.SecondCard',
    ];

    $sort = (string)$request->query('sort', 'AccessTime');
    $dir  = strtolower((string)$request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
    $sortCol = $allowedSort[$sort] ?? 'p.AccessTime';

    $query = \DB::table('passage as p')
        ->leftJoin(
            'mergedvisitcontrol as m',
            'm.idmergedvisitcontrol',
            '=',
            'p.IdMergedVisitControl'
        )
        ->select([
            'p.AccessTime',
            'p.DoorID',
            'p.AccessType',
            'p.AccessMethod',
            'm.LastName',
            'm.FirstName',
            'm.MiddleName',
            'm.Department',
            'm.Position',
            'm.FirstCard',
            'm.SecondCard',
        ]);

    if ($from) {
        $query->where('p.AccessTime', '>=', $from . ' 00:00:00');
    }

    if ($to) {
        $query->where('p.AccessTime', '<=', $to . ' 23:59:59');
    }

    if ($q !== '') {
        $query->where(function ($w) use ($q) {
            $w->where('m.LastName', 'like', "%{$q}%")
              ->orWhere('m.FirstName', 'like', "%{$q}%")
              ->orWhere('m.MiddleName', 'like', "%{$q}%")
              ->orWhere('m.Department', 'like', "%{$q}%")
              ->orWhere('m.Position', 'like', "%{$q}%")
              ->orWhere('p.DoorID', 'like', "%{$q}%");
        });
    }

    return $query->orderBy($sortCol, $dir);
}




public function export(Request $request)
{
    $reportAccess = app(\App\Services\LicenseService::class)->reportAccess();
    if (empty($reportAccess['allowed_download'])) {
        abort(403, 'Report download is blocked by license.');
    }

    @ini_set('memory_limit', '512M');
    @set_time_limit(300);

    $params = $this->resolveReportParams($request);
    $q = $params['q'];
    $department = $params['department'];
    $position = $params['position'];
    $breakMinutes = $params['breakMinutes'];
    $periodStart = $params['periodStart'];
    $periodEnd = $params['periodEnd'];
    $qFrom = $params['qFrom'];
    $qTo = $params['qTo'];

    $rows = $this->getDailyRowsForPeriod(
        $qFrom,
        $qTo,
        $periodStart,
        $periodEnd,
        $department,
        $position,
        $q,
        $breakMinutes
    );

    usort($rows, function ($a, $b) {
        return strcmp((string)$a['fio'], (string)$b['fio']);
    });

    $dayDates = [];
    $cursorDay = (clone $periodStart)->startOfDay();
    $periodEndDay = (clone $periodEnd)->startOfDay();
    while ($cursorDay->lte($periodEndDay)) {
        $dayDates[] = $cursorDay->toDateString();
        $cursorDay->addDay();
    }

    $daysInPeriod = count($dayDates);
    $weekRanges = [];
    $w = 1;
    for ($startDay = 0; $startDay < $daysInPeriod; $startDay += 7) {
        $endDay = min($startDay + 6, $daysInPeriod - 1);
        $weekRanges[] = [
            'index' => $w,
            'start' => $startDay,
            'end' => $endDay,
        ];
        $w++;
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Табель');

    $fixedCols = 3;
    $weeklyTotalCount = max(0, count($weekRanges) - 1);
    $totalCols = $fixedCols + $daysInPeriod + $weeklyTotalCount + 1;
    $lastColLetter = preg_replace('/\d+$/', '', $this->xlCell($totalCols, 1));

    $sheet->mergeCells("A1:{$lastColLetter}1");
    $sheet->setCellValue('A1', 'Отчет по отработанному времени');

    $sheet->mergeCells("A2:{$lastColLetter}2");
    $sheet->setCellValue(
        'A2',
        'Период: ' . $periodStart->format('d.m.Y') . ' - ' . $periodEnd->format('d.m.Y')
        . ' | Разрешенный перерыв: ' . $breakMinutes . ' мин'
        . ' | Сформирован: ' . now()->format('d.m.Y H:i')
    );

    $headerRow = 4;
    $dataRow = 5;

    $sheet->setCellValue($this->xlCell(1, $headerRow), 'ФИО сотрудника');
    $sheet->setCellValue($this->xlCell(2, $headerRow), 'Должность');
    $sheet->setCellValue($this->xlCell(3, $headerRow), 'Подразделение');

    $col = 4;
    $weekEndToIdx = [];
    $summaryCols = [];
    $lastWeekIndex = count($weekRanges);
    foreach ($weekRanges as $range) {
        $weekEndToIdx[$range['end']] = $range['index'];
    }

    for ($day = 0; $day < $daysInPeriod; $day++) {
        $sheet->setCellValueExplicit(
            $this->xlCell($col, $headerRow),
            Carbon::parse($dayDates[$day])->format('j.m'),
            DataType::TYPE_STRING
        );
        $col++;

        if (isset($weekEndToIdx[$day]) && (int)$weekEndToIdx[$day] < $lastWeekIndex) {
            $sheet->setCellValue($this->xlCell($col, $headerRow), 'Итог нед. ' . $weekEndToIdx[$day]);
            $summaryCols[] = $col;
            $col++;
        }
    }

    $sheet->setCellValue($this->xlCell($col, $headerRow), 'Итог за период');
    $summaryCols[] = $col;

    $currentRow = $dataRow;

    foreach ($rows as $row) {
        $dailySeconds = $row['daily_seconds'];

        $sheet->setCellValue($this->xlCell(1, $currentRow), (string)$row['fio']);
        $sheet->setCellValue($this->xlCell(2, $currentRow), (string)$row['position']);
        $sheet->setCellValue($this->xlCell(3, $currentRow), (string)$row['department']);

        $col = 4;
        $monthSeconds = 0;
        $runningWeekTotalSeconds = 0;

        foreach ($weekRanges as $range) {
            for ($day = $range['start']; $day <= $range['end']; $day++) {
                $dayKey = $dayDates[$day];
                $daySeconds = (int)($dailySeconds[$dayKey] ?? 0);
                $monthSeconds += $daySeconds;

                $sheet->setCellValue($this->xlCell($col, $currentRow), round($daySeconds / 3600, 2));
                $col++;
            }

            if ((int)$range['index'] < $lastWeekIndex) {
                $weekSeconds = 0;
                for ($day = $range['start']; $day <= $range['end']; $day++) {
                    $dayKey = $dayDates[$day];
                    $weekSeconds += (int)($dailySeconds[$dayKey] ?? 0);
                }

                $runningWeekTotalSeconds += $weekSeconds;
                $sheet->setCellValue($this->xlCell($col, $currentRow), round($runningWeekTotalSeconds / 3600, 2));
                $col++;
            }
        }

        $sheet->setCellValue($this->xlCell($col, $currentRow), round($monthSeconds / 3600, 2));

        $currentRow++;
    }

    if (empty($rows)) {
        $sheet->mergeCells("A{$dataRow}:{$lastColLetter}{$dataRow}");
        $sheet->setCellValue("A{$dataRow}", 'Нет данных за выбранный период.');
    }

    $sheet->getStyle("A1:A2")->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle("A1:{$lastColLetter}2")
        ->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

    $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")->getFont()->setBold(true);
    $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")
        ->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

    $sheet->getColumnDimension('A')->setWidth(38);
    $sheet->getColumnDimension('B')->setWidth(28);
    $sheet->getColumnDimension('C')->setWidth(30);
    for ($i = 4; $i <= $totalCols; $i++) {
        $colLetter = preg_replace('/\d+$/', '', $this->xlCell($i, 1));
        if (in_array($i, $summaryCols, true)) {
            $sheet->getColumnDimension($colLetter)->setWidth(13);
        } else {
            $sheet->getColumnDimension($colLetter)->setWidth(8);
        }
    }

    $lastDataRow = !empty($rows) ? ($currentRow - 1) : $dataRow;
    $sheet->freezePane('D5');
    $sheet->setAutoFilter("A{$headerRow}:{$lastColLetter}{$lastDataRow}");

    $sheet->getStyle("A1:{$lastColLetter}{$lastDataRow}")
        ->getAlignment()
        ->setWrapText(true);

    if (!empty($rows)) {
        for ($r = $dataRow; $r <= $lastDataRow; $r++) {
            if ((($r - $dataRow) % 2) === 1) {
                $sheet->getStyle("A{$r}:{$lastColLetter}{$r}")
                    ->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('FFE8F5E9');
            }
        }
    }

    foreach ($summaryCols as $summaryCol) {
        $summaryColLetter = preg_replace('/\d+$/', '', $this->xlCell($summaryCol, 1));
        $sheet->getStyle("{$summaryColLetter}{$headerRow}:{$summaryColLetter}{$lastDataRow}")
            ->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFE5E7EB');

        $sheet->getStyle("{$summaryColLetter}{$headerRow}:{$summaryColLetter}{$lastDataRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
            ->getColor()
            ->setARGB('FF9CA3AF');
    }

    $sheet->getStyle("A1:{$lastColLetter}{$lastDataRow}")
        ->getBorders()
        ->getAllBorders()
        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
        ->getColor()
        ->setARGB('FF9CA3AF');

    $sheet->getStyle($this->xlCell(4, $dataRow) . ':' . $this->xlCell($totalCols, $lastDataRow))
        ->getNumberFormat()
        ->setFormatCode('0.00');

    $fileName = 'Отчет по отработанному времени за '
        . $periodStart->format('d.m.Y')
        . '_'
        . $periodEnd->format('d.m.Y')
        . '.xlsx';

    return new StreamedResponse(function () use ($spreadsheet) {
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }, 200, [
        'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        'Cache-Control'       => 'max-age=0',
    ]);
}

private function calcPeriodDailyRows(Collection $events, Carbon $periodStart, Carbon $periodEnd, int $allowedBreakMinutes): array
{
    $periodStartTs = (clone $periodStart)->startOfDay()->getTimestamp();
    $periodEndExclusiveTs = (clone $periodEnd)->addDay()->startOfDay()->getTimestamp();

    $dayKeys = [];
    $dayCursor = (clone $periodStart)->startOfDay();
    $periodEndDay = (clone $periodEnd)->startOfDay();
    while ($dayCursor->lte($periodEndDay)) {
        $dayKeys[] = $dayCursor->toDateString();
        $dayCursor->addDay();
    }

    $allowedBreakSec = max(0, min(240, $allowedBreakMinutes)) * 60;
    $shiftBreakSec = 2 * 3600;
    $maxShiftSec = 24 * 3600;
    $dupResetSec = max(0, (int)config('worktime.dup_in_reset_minutes', 15)) * 60;

    $grouped = [];
    foreach ($events as $e) {
        $employeeId = (int)($e->IdMergedVisitControl ?? 0);
        if ($employeeId <= 0) continue;

        if (!isset($grouped[$employeeId])) {
            $fio = trim(
                ((string)($e->LastName ?? '')) . ' ' .
                ((string)($e->FirstName ?? '')) . ' ' .
                ((string)($e->MiddleName ?? ''))
            );

            $grouped[$employeeId] = [
                'employee_id' => $employeeId,
                'fio' => $fio,
                'position' => (string)($e->Position ?? ''),
                'department' => (string)($e->Department ?? ''),
                'events' => [],
            ];
        }

        $grouped[$employeeId]['events'][] = $e;
    }

    $result = [];

    foreach ($grouped as $employeeId => $person) {
        $eventsList = $person['events'];
        usort($eventsList, fn($a, $b) => strcmp((string)$a->AccessTime, (string)$b->AccessTime));

        $segments = [];
        $openInTs = null;
        $lastOutTs = null;

        foreach ($eventsList as $ev) {
            $normalizedType = $this->normalizeAccessType((string)($ev->AccessType ?? ''));
            if ($normalizedType === null) continue;

            try {
                $ts = Carbon::parse($ev->AccessTime)->getTimestamp();
            } catch (\Throwable $ex) {
                continue;
            }

            if ($normalizedType === 'in') {
                // дубль входа: первый вход держим, но при длинном разрыве перезаписываем более поздним
                if ($openInTs === null) {
                    $openInTs = $ts;
                } elseif ($dupResetSec > 0 && ($ts - $openInTs) >= $dupResetSec) {
                    $openInTs = $ts;
                }
                continue;
            }

            // normalizedType === 'out'
            // дубль выхода подряд без нового входа — игнорируем
            if ($openInTs === null) {
                $lastOutTs = $ts;
                continue;
            }

            if ($ts <= $openInTs) {
                $openInTs = null;
                $lastOutTs = $ts;
                continue;
            }

            $dur = $ts - $openInTs;
            if ($dur <= $maxShiftSec) {
                $segments[] = [$openInTs, $ts];
            }

            $openInTs = null;
            $lastOutTs = $ts;
        }

        $dailySeconds = [];
        foreach ($dayKeys as $key) {
            $dailySeconds[$key] = 0;
        }

        if (!empty($segments)) {
            usort($segments, fn($a, $b) => $a[0] <=> $b[0]);

            $flushShift = function (array $shiftSegments) use (
                &$dailySeconds,
                $allowedBreakSec,
                $maxShiftSec,
                $periodStartTs,
                $periodEndExclusiveTs
            ) {
                if (empty($shiftSegments)) return;

                $shiftStart = (int)$shiftSegments[0][0];
                $shiftCapEnd = $shiftStart + $maxShiftSec;

                $count = count($shiftSegments);
                for ($i = 0; $i < $count; $i++) {
                    $segStart = (int)$shiftSegments[$i][0];
                    $segEnd = (int)$shiftSegments[$i][1];

                    $segEnd = min($segEnd, $shiftCapEnd);
                    $this->addIntervalToDailyByDate(
                        $dailySeconds,
                        $segStart,
                        $segEnd,
                        $periodStartTs,
                        $periodEndExclusiveTs
                    );

                    if ($i < $count - 1 && $allowedBreakSec > 0) {
                        $gapStart = $segEnd;
                        $nextStart = (int)$shiftSegments[$i + 1][0];
                        $gap = $nextStart - $gapStart;

                        if ($gap > 0) {
                            $allowedPart = min($gap, $allowedBreakSec);
                            $breakEnd = min($gapStart + $allowedPart, $shiftCapEnd);
                            $this->addIntervalToDailyByDate(
                                $dailySeconds,
                                $gapStart,
                                $breakEnd,
                                $periodStartTs,
                                $periodEndExclusiveTs
                            );
                        }
                    }
                }
            };

            $shiftSegments = [];
            $prevOut = null;
            foreach ($segments as $segment) {
                [$segStart, $segEnd] = $segment;

                if (empty($shiftSegments)) {
                    $shiftSegments[] = [$segStart, $segEnd];
                    $prevOut = $segEnd;
                    continue;
                }

                $gap = $segStart - $prevOut;
                if ($gap > $shiftBreakSec) {
                    $flushShift($shiftSegments);
                    $shiftSegments = [[$segStart, $segEnd]];
                } else {
                    $shiftSegments[] = [$segStart, $segEnd];
                }
                $prevOut = $segEnd;
            }

            $flushShift($shiftSegments);
        }

        $result[] = [
            'employee_id' => (int)$employeeId,
            'fio' => $person['fio'],
            'position' => $person['position'],
            'department' => $person['department'],
            'daily_seconds' => $dailySeconds,
        ];
    }

    return $result;
}

private function normalizeAccessType(string $rawType): ?string
{
    $type = mb_strtolower(trim($rawType), 'UTF-8');

    if (in_array($type, ['вход', 'in', 'enter', 'entry'], true)) {
        return 'in';
    }
    if (in_array($type, ['выход', 'out', 'exit'], true)) {
        return 'out';
    }

    return null;
}

private function addIntervalToDaily(
    array &$dailySeconds,
    int $startTs,
    int $endTs,
    int $monthStartTs,
    int $monthEndExclusiveTs
): void {
    $start = max($startTs, $monthStartTs);
    $end = min($endTs, $monthEndExclusiveTs);

    if ($end <= $start) return;

    $cursor = $start;
    while ($cursor < $end) {
        $dayStart = Carbon::createFromTimestamp($cursor)->startOfDay()->getTimestamp();
        $nextDay = $dayStart + 86400;
        $partEnd = min($end, $nextDay);

        $day = (int)Carbon::createFromTimestamp($cursor)->day;
        $dailySeconds[$day] = (int)($dailySeconds[$day] ?? 0) + ($partEnd - $cursor);

        $cursor = $partEnd;
    }
}

private function addIntervalToDailyByDate(
    array &$dailySeconds,
    int $startTs,
    int $endTs,
    int $periodStartTs,
    int $periodEndExclusiveTs
): void {
    $start = max($startTs, $periodStartTs);
    $end = min($endTs, $periodEndExclusiveTs);

    if ($end <= $start) return;

    $cursor = $start;
    while ($cursor < $end) {
        $dayStart = Carbon::createFromTimestamp($cursor)->startOfDay()->getTimestamp();
        $nextDay = $dayStart + 86400;
        $partEnd = min($end, $nextDay);

        $dayKey = Carbon::createFromTimestamp($cursor)->toDateString();
        $dailySeconds[$dayKey] = (int)($dailySeconds[$dayKey] ?? 0) + ($partEnd - $cursor);

        $cursor = $partEnd;
    }
}

private function buildReportRowsQuery(Request $request)
{
    $from = $request->query('from');
    $to   = $request->query('to');
    $q    = trim((string)$request->query('q', ''));

    if (!$from && !$to) {
        $from = now()->startOfMonth()->toDateString();
        $to   = now()->endOfMonth()->toDateString();
    }

    // сорт
    $allowedSort = [
        'time' => 'p.AccessTime',
        'fio'  => 'Fio',            // сорт по алиасу (см. selectRaw ниже)
        'door' => 'Door',
        // добавь ключи, которые реально используешь в UI
    ];
    $sort = (string)$request->query('sort', 'time');
    $dir  = strtolower((string)$request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
    $sortCol = $allowedSort[$sort] ?? 'p.AccessTime';

    // === ВАЖНО: ниже должна быть ТОЧНАЯ ЛОГИКА, как на странице ===
    $query = \DB::connection('remote_mysql')
        ->table('passage as p')
        ->leftJoin('mergedvisitcontrol as m', 'm.idmergedvisitcontrol', '=', 'p.IdMergedVisitControl')

        // если на странице ты ещё джойнишь двери/периметры/локации — добавляй те же join'ы

        ->select([
            'p.AccessTime as AccessTime',
            'm.Department as Department',
            'm.Position as Position',
            'm.FirstCard as FirstCard',
            'm.SecondCard as SecondCard',
        ])

        // ФИО как на странице (пример — подстрой под свой вывод)
        ->selectRaw("TRIM(CONCAT(IFNULL(m.LastName,''),' ',IFNULL(m.FirstName,''),' ',IFNULL(m.MiddleName,''))) as Fio")

        // Door как на странице (пример: если у тебя сейчас просто DoorID — оставь DoorID)
        ->selectRaw("CAST(p.DoorID AS CHAR) as Door")

        // Текстовые поля как на странице (примерные CASE, подстрой под твою логику отображения)
        ->selectRaw("
            CASE p.AccessType
                WHEN 1 THEN 'Вход'
                WHEN 2 THEN 'Выход'
                ELSE CAST(p.AccessType AS CHAR)
            END as AccessTypeTxt
        ")
        ->selectRaw("
            CASE p.AccessMethod
                WHEN 1 THEN 'Карта'
                WHEN 2 THEN 'Лицо'
                ELSE CAST(p.AccessMethod AS CHAR)
            END as AccessMethodTxt
        ");

    if ($from) $query->where('p.AccessTime', '>=', $from . ' 00:00:00');
    if ($to)   $query->where('p.AccessTime', '<=', $to . ' 23:59:59');

    if ($q !== '') {
        $query->where(function ($w) use ($q) {
            $w->where('m.LastName', 'like', "%{$q}%")
              ->orWhere('m.FirstName', 'like', "%{$q}%")
              ->orWhere('m.MiddleName', 'like', "%{$q}%")
              ->orWhere('m.Department', 'like', "%{$q}%")
              ->orWhere('m.Position', 'like', "%{$q}%")
              ->orWhere('p.DoorID', 'like', "%{$q}%");
        });
    }

    // orderBy должен быть всегда (для chunk)
    return $query->orderBy($sortCol, $dir)->orderBy('p.AccessTime', 'desc');
}


private function reportColumns(): array
{
    return [
        'AccessTime'    => 'Дата/время',
        'Door'          => 'Дверь',          // человекочитаемо как на странице
        'AccessTypeTxt' => 'Тип доступа',    // текст как на странице
        'AccessMethodTxt'=> 'Метод доступа', // текст как на странице
        'Fio'           => 'ФИО',            // как на странице (LastName + инициалы и т.п.)
        'Department'    => 'Отдел',
        'Position'      => 'Должность',

        // добавь/убери строго то, что реально показываешь
    ];
}


private function extractPlant(string $doorId): string
{
    $s = mb_strtoupper(trim($doorId), 'UTF-8');

    if ($s === '') return '—';

    if (mb_strpos($s, 'ШБФ') !== false) return 'ШБФ';
    if (mb_strpos($s, 'ЛК')  !== false) return 'ЛК';

    // если у DoorID есть понятное значение — оставим его
    return $s;
}

private function xlCell(int $col, int $row): string
{
    $colLetters = '';
    while ($col > 0) {
        $col--;
        $colLetters = chr(65 + ($col % 26)) . $colLetters;
        $col = intdiv($col, 26);
    }
    return $colLetters . $row;
}



}
