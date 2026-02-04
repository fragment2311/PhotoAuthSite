<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Collection\CellsFactory;
use PhpOffice\PhpSpreadsheet\CachedObjectStorage\CacheBase;
use PhpOffice\PhpSpreadsheet\CachedObjectStorageFactory;

class PassageController extends Controller
{
    public function index(Request $request)
    {
        // =========================
        // Фильтры (как в index.blade.php)
        // =========================
        $from = $request->query('from');
        $to   = $request->query('to');
        $q    = trim((string)$request->query('q', ''));
        $department = $request->query('department');
$position   = $request->query('position');
$breakMinutes = (int) $request->query('break_minutes', 20);
if ($breakMinutes < 0) $breakMinutes = 0;
if ($breakMinutes > 240) $breakMinutes = 240; // защита, можно поменять


        // нормализация строки поиска для копипаста (NBSP и т.п.)
$q = preg_replace('/[\x{00A0}\x{2007}\x{202F}]/u', ' ', $q);
$q = preg_replace('/\s+/u', ' ', $q);
$q = trim($q);
$rows = $this->buildReportQuery($request)->paginate(50)->withQueryString();


// дефолт: текущий месяц
if (!$from && !$to) {
    $from = now()->startOfMonth()->toDateString();
    $to   = now()->endOfMonth()->toDateString();
}

        // валидация дат
        if ($from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = null;
        if ($to   && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = null;

        $periodFrom = $from ? Carbon::parse($from)->startOfDay() : now()->subDays(7)->startOfDay();
        $periodTo   = $to   ? Carbon::parse($to)->endOfDay()     : now()->endOfDay();

        // буфер для смен на границах
        $qFrom = (clone $periodFrom)->subDay();
        $qTo   = (clone $periodTo)->addDay();


        // =========================
// Периметр (двери входа/выхода из здания)
// =========================
$perimeter = config('worktime.perimeter', []);
$perimeterMatch = $perimeter['match'] ?? 'contains'; // 'exact' | 'contains'
$perimeterDoors = $perimeter['doors'] ?? [];


        // =========================
        // Сортировка (allowlist)
        // =========================
$sort = $request->query('sort', 'fio');
$dir  = strtolower((string)$request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $allowedSort = [
            'fio'         => 'fio',
            'id'          => 'employee_id',
            'department'  => 'department',
            'position'    => 'position',
            'worked_hours'=> 'worked_hours',
        ];
        if (!isset($allowedSort[$sort])) $sort = 'fio';


        // =========================
        // Дедуп: одинаковые события -> MIN(IdPassage)
        // =========================
$dedup = DB::table('passage')
    ->selectRaw('MIN(IdPassage) as IdPassage')
    ->whereBetween('AccessTime', [$qFrom, $qTo])
    ->whereNotNull('IdMergedVisitControl')
    ->when(!empty($perimeterDoors), function ($qq) use ($perimeterDoors, $perimeterMatch) {
        $qq->where(function ($w) use ($perimeterDoors, $perimeterMatch) {
            foreach ($perimeterDoors as $d) {
                $d = trim((string)$d);
                if ($d === '') continue;

                if ($perimeterMatch === 'exact') {
                    $w->orWhereRaw('TRIM(DoorID) = ?', [$d]);
                } else {
                    $w->orWhere('DoorID', 'like', '%' . $d . '%');
                }
            }
            
        });
    })
    ->groupBy('IdMergedVisitControl', 'AccessTime', 'AccessType', 'DoorID');



$filters = [
    'from'       => $from,
    'to'         => $to,
    'q'          => $q,
    'department' => $department,
    'position'   => $position,
    'sort'       => $sort,
    'dir'        => $dir,
    'break_minutes' => $breakMinutes,
];



        // =========================
        // События + человек
        // =========================
        $eventsQ = DB::table('passage as p')
            ->joinSub($dedup, 'd', function ($join) {
                $join->on('p.IdPassage', '=', 'd.IdPassage');
            })
            ->join('MergedVisitControl as m', 'm.idmergedvisitcontrol', '=', 'p.IdMergedVisitControl')
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
                ->when($department, function ($q) use ($department) {
    $q->where('m.Department', $department);
})
->when($position, function ($q) use ($position) {
    $q->where('m.Position', $position);
})
            ->orderBy('p.IdMergedVisitControl')
            ->orderBy('p.AccessTime');

        // Поиск q: ФИО / таб.№ / устройство
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

        $events = $eventsQ->get();
        $testId = 614;

$ev614 = $events->filter(fn($e) => (int)$e->IdMergedVisitControl === $testId)->values();

$departments = DB::table('mergedvisitcontrol')
    ->whereNotNull('Department')
    ->where('Department', '<>', '')
    ->distinct()
    ->orderBy('Department')
    ->pluck('Department');

$positions = DB::table('mergedvisitcontrol')
    ->whereNotNull('Position')
    ->where('Position', '<>', '')
    ->distinct()
    ->orderBy('Position')
    ->pluck('Position');


// logger()->info('[WORKHOURS] 614 events in query', [
//     'count' => $ev614->count(),
//     'min' => $ev614->min('AccessTime'),
//     'max' => $ev614->max('AccessTime'),
// ]);

// // покажем первые/последние 5 событий
// logger()->info('[WORKHOURS] 614 first5', $ev614->sortBy('AccessTime')->take(5)->map(fn($e)=>[$e->AccessTime,$e->AccessType])->values()->all());
// logger()->info('[WORKHOURS] 614 last5',  $ev614->sortByDesc('AccessTime')->take(5)->map(fn($e)=>[$e->AccessTime,$e->AccessType])->values()->all());

$stat = [];
foreach ($events as $e) {
    $id = (int)$e->IdMergedVisitControl;
    if (!isset($stat[$id])) $stat[$id] = ['in'=>0,'out'=>0];
    $type = trim((string)$e->AccessType);
    if ($type === 'Вход')  $stat[$id]['in']++;
    if ($type === 'Выход') $stat[$id]['out']++;
}

$onlyIn = 0; $onlyOut = 0; $both = 0;
foreach ($stat as $s) {
    if ($s['in'] > 0 && $s['out'] > 0) $both++;
    elseif ($s['in'] > 0) $onlyIn++;
    elseif ($s['out'] > 0) $onlyOut++;
}

// logger()->info('[WORKHOURS] per-employee direction stat', [
//     'employees_total' => count($stat),
//     'both_in_out' => $both,
//     'only_in' => $onlyIn,
//     'only_out' => $onlyOut,
// ]);

        // =========================
        // Агрегация: часы по людям
        // =========================
        $rows = $this->calcWorkedHours($events, $periodFrom, $periodTo, $breakMinutes);

// logger()->info('[WORKHOURS] first_row', $rows[0] ?? []);

        // сортировка
        usort($rows, function ($a, $b) use ($sort, $dir) {
            $ka = $a[$sort];
            $kb = $b[$sort];

            if ($ka == $kb) return 0;
            $res = ($ka < $kb) ? -1 : 1;
            return $dir === 'asc' ? $res : -$res;
        });

       // =========================
// Пагинация массива
// =========================
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
    'rows'        => $paginator,
    'departments' => $departments,
    'positions'   => $positions,
    'filters'     => $filters,
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
    // Экспорт может быть тяжёлым по времени/памяти, но тут строк обычно меньше (по людям).
    @ini_set('memory_limit', '512M');
    @set_time_limit(300);

    // =========================
    // Фильтры (как в index)
    // =========================
    $from = $request->query('from');
    $to   = $request->query('to');
    $q    = trim((string)$request->query('q', ''));
    $department = $request->query('department');
    $position   = $request->query('position');

    // нормализация q (как в index)
    $q = preg_replace('/[\x{00A0}\x{2007}\x{202F}]/u', ' ', $q);
    $q = preg_replace('/\s+/u', ' ', $q);
    $q = trim($q);

    // дефолт: текущий месяц
    if (!$from && !$to) {
        $from = now()->startOfMonth()->toDateString();
        $to   = now()->endOfMonth()->toDateString();
    }

    // валидация дат
    if ($from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = null;
    if ($to   && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = null;

    $periodFrom = $from ? Carbon::parse($from)->startOfDay() : now()->subDays(7)->startOfDay();
    $periodTo   = $to   ? Carbon::parse($to)->endOfDay()     : now()->endOfDay();

    // буфер для смен на границах (как в index)
    $qFrom = (clone $periodFrom)->subDay();
    $qTo   = (clone $periodTo)->addDay();

    // =========================
    // Периметр (как в index)
    // =========================
    $perimeter = config('worktime.perimeter', []);
    $perimeterMatch = $perimeter['match'] ?? 'contains'; // 'exact' | 'contains'
    $perimeterDoors = $perimeter['doors'] ?? [];

    // =========================
    // Сортировка (как в index)
    // =========================
    $sort = (string)$request->query('sort', 'fio');
    $dir  = strtolower((string)$request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

    $allowedSort = [
        'fio'          => 'fio',
        'id'           => 'employee_id',
        'department'   => 'department',
        'position'     => 'position',
        'worked_hours' => 'worked_hours',
    ];
    if (!isset($allowedSort[$sort])) $sort = 'fio';

    // =========================
    // Дедуп (как в index)
    // =========================
    $dedup = DB::table('passage')
        ->selectRaw('MIN(IdPassage) as IdPassage')
        ->whereBetween('AccessTime', [$qFrom, $qTo])
        ->whereNotNull('IdMergedVisitControl')
        ->when(!empty($perimeterDoors), function ($qq) use ($perimeterDoors, $perimeterMatch) {
            $qq->where(function ($w) use ($perimeterDoors, $perimeterMatch) {
                foreach ($perimeterDoors as $d) {
                    $d = trim((string)$d);
                    if ($d === '') continue;

                    if ($perimeterMatch === 'exact') {
                        $w->orWhereRaw('TRIM(DoorID) = ?', [$d]);
                    } else {
                        $w->orWhere('DoorID', 'like', '%' . $d . '%');
                    }
                }
            });
        })
        ->groupBy('IdMergedVisitControl', 'AccessTime', 'AccessType', 'DoorID');

    // =========================
    // События + человек (как в index)
    // =========================
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

    // Поиск q (как в index)
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

    $events = $eventsQ->get();

    // =========================
    // Агрегация: часы по людям (как в index)
    // =========================
    $rows = $this->calcWorkedHours($events, $periodFrom, $periodTo);

    // сортировка (как в index)
    usort($rows, function ($a, $b) use ($sort, $dir) {
        $ka = $a[$sort];
        $kb = $b[$sort];

        if ($ka == $kb) return 0;
        $res = ($ka < $kb) ? -1 : 1;
        return $dir === 'asc' ? $res : -$res;
    });

    // =========================
    // Excel (вид как на странице)
    // =========================
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Проходы сотрудников');

    $headers = ['ФИО', 'Завод', 'Подразделение', 'Должность', 'Отработано, ч'];

    foreach ($headers as $i => $h) {
        $sheet->setCellValue($this->xlCell($i + 1, 1), $h);
    }
    $sheet->freezePane('A2');

    $r = 2;
    foreach ($rows as $row) {
        $sheet->setCellValue($this->xlCell(1, $r), (string)($row['fio'] ?? ''));
        $sheet->setCellValue($this->xlCell(2, $r), (string)($row['plant'] ?? '—'));
        $sheet->setCellValue($this->xlCell(3, $r), (string)($row['department'] ?? ''));
        $sheet->setCellValue($this->xlCell(4, $r), (string)($row['position'] ?? ''));
        $sheet->setCellValue($this->xlCell(5, $r), (string)($row['worked_hours'] ?? 0));
        $r++;
    }

foreach (range('A', 'E') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
    $fileName = 'Отчет отработанное время ' . $periodFrom->format('Y-m-d') . '_' . $periodTo->format('Y-m-d') . '.xlsx';

    return new StreamedResponse(function () use ($spreadsheet) {
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }, 200, [
        'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        'Cache-Control'       => 'max-age=0',
    ]);
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
