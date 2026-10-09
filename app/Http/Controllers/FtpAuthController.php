<?php

namespace App\Http\Controllers;

use App\Services\BootstrapConfigService;
use App\Services\ErrorNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Webuser;
use App\Models\Role;
use App\Support\MasterAuthUser;
use Carbon\Carbon;

class FtpAuthController extends Controller
{
    private const TRANSPARENT_PIXEL_DATA_URI = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

    // === Регистрация (форма) ===
    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('gallery', ['dir' => $this->galleryRootDir()]);
        }
        return view('register');
    }

    // === Регистрация (действие) ===
    public function register(Request $req)
    {
        $req->validate([
            'login'    => 'required|string|min:3|max:200|unique:webusers,Login',
            'password' => 'required|string|confirmed|min:6',
        ], [
            'login.required'    => 'Поле «Логин» обязательно.',
            'login.unique'      => 'Такой логин уже используется.',
            'password.required' => 'Поле «Пароль» обязательно.',
            'password.confirmed'=> 'Пароли не совпадают.',
            'password.min'      => 'Пароль должен содержать минимум 6 символов.',
        ]);

        // Запрет одинаковых паролей
        foreach (DB::table('webusers')->pluck('Password') as $hash) {
            if (Hash::check($req->password, $hash)) {
                return back()
                    ->withErrors(['password' => 'Этот пароль уже используется.'])
                    ->withInput(['login' => $req->login]);
            }
        }

        DB::table('webusers')->insert([
            'Login'      => $req->login,
            'Password'   => Hash::make($req->password),
            'LastUpdate' => now(),
        ]);

        return redirect()->route('login')
            ->with('status', 'Регистрация прошла успешно. Войдите, пожалуйста.');
    }

    // === Форма входа ===
    public function showLogin()
    {
        $bootstrap = app(BootstrapConfigService::class);
        $masterSession = (bool) request()->session()->get('master_auth', false);
        if (!$bootstrap->isConfigured()) {
            if (Auth::check() || $masterSession) {
                Auth::logout();
                request()->session()->invalidate();
                request()->session()->regenerateToken();
            }

            request()->session()->forget('setup_access_granted');
            return redirect()->route('setup.auth', request()->only('setup_key'));
        }

        if (Auth::check() || $masterSession) {
            return redirect()->route('gallery', ['dir' => $this->galleryRootDir()])
                ->withHeaders([
                    'Cache-Control' => 'no-store, no-cache, must-revalidate',
                    'Pragma'        => 'no-cache',
                    'Expires'       => '0',
                ]);
        }

        return response()
            ->view('login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    // === Редирект по ролям ===
    protected function redirectByRole()
    {
        return redirect()->route('gallery', ['dir' => $this->galleryRootDir()]);
    }

    // === Логин (действие) ===
    public function login(Request $request)
    {
        $bootstrap = app(BootstrapConfigService::class);
        $masterSession = (bool) $request->session()->get('master_auth', false);
        if (!$bootstrap->isConfigured()) {
            if (Auth::check() || $masterSession) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            $request->session()->forget('setup_access_granted');
            return redirect()->route('setup.auth', $request->only('setup_key'));
        }

        $login = trim((string) $request->input('login', ''));
        $password = (string) $request->input('password', '');
        $throttleKey = $this->throttleKey($request, $login === '' ? 'master' : $login);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return back()
                ->withErrors(['login' => 'Слишком много попыток входа. Попробуйте через минуту.'])
                ->withInput($request->except('password'));
        }

        if ($bootstrap->verifyMasterCredentials($login, $password)) {
            Auth::login(new MasterAuthUser($bootstrap->getMasterLogin()));
            $request->session()->regenerate();
            $request->session()->put('setup_access_granted', true);
            $request->session()->put('master_auth', true);
            RateLimiter::clear($throttleKey);

            return $this->redirectByRole();
        }

        $user = Webuser::where('Login', $login)->first();

        if ($user && ! $user->IsActive) {
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withErrors(['login' => 'Неверный логин или пароль'])
                ->withInput($request->except('password'));
        }

        $credentials = [
            'Login'    => $login,
            'password' => $password,
        ];

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            $request->session()->forget('master_auth');
            RateLimiter::clear($throttleKey);

            return $this->redirectByRole();
        }

        RateLimiter::hit($throttleKey, 60);

        return back()
            ->withErrors(['login' => 'Неверный логин или пароль'])
            ->withInput($request->except('password'));
    }

    private function throttleKey(Request $request, string $login): string
    {
        return Str::lower(trim($login)) . '|' . $request->ip();
    }

    // === Выход ===
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma'        => 'no-cache',
                'Expires'       => '0',
            ]);
    }

    // === Галерея (только для авторизованных) ===
    public function gallery(Request $req, $dir = null)
    {
        $base           = $this->galleryRootDir();
        $current        = $dir ? trim($dir, '/') : $base;
        $httpBaseUrl    = $this->photosHttpBase();
        $photoStorage   = $this->photoStorageStatus();
        $storageUnavailable = !($photoStorage['available'] ?? false);
        $storageUnavailableReason = (string) ($photoStorage['reason'] ?? '');
        $photosBasePath = $this->photosStaffPath();
        $rootSections   = $this->galleryRootSections();
        $rootSectionsNorm = array_values(array_filter(array_map(
            static fn (string $v): string => mb_strtolower(trim($v)),
            $rootSections
        ), static fn (string $v): bool => $v !== ''));

        // Разбиваем путь на части (абсолютные + относительные к base)
        $parts = explode('/', $current);
        $partsNorm = array_values(array_filter(array_map(
            static fn (string $v): string => mb_strtolower(trim($v)),
            $parts
        ), static fn (string $v): bool => $v !== ''));

        $basePartsNorm = array_values(array_filter(array_map(
            static fn (string $v): string => mb_strtolower(trim($v)),
            explode('/', $base)
        ), static fn (string $v): bool => $v !== ''));

        $relativePartsNorm = $partsNorm;
        if (!empty($basePartsNorm) && count($partsNorm) >= count($basePartsNorm)) {
            $prefix = array_slice($partsNorm, 0, count($basePartsNorm));
            if ($prefix === $basePartsNorm) {
                $relativePartsNorm = array_slice($partsNorm, count($basePartsNorm));
            }
        }

        // Где мы? В точке (section) и это подпапка точки?
        $isShbfOrLk = isset($relativePartsNorm[0])
            && in_array($relativePartsNorm[0], $rootSectionsNorm, true);
        $isShbfOrLkSubfolder = $isShbfOrLk && count($relativePartsNorm) > 2;

        // ФИО для шапки
        $personId = collect($parts)->reverse()->first(fn($seg) => is_numeric($seg));
        $personName = null;
        if ($personId) {
            $personName = DB::table('mergedvisitcontrol')
                ->where('idmergedvisitcontrol', $personId)
                ->value(DB::raw("CONCAT_WS(' ', LastName, FirstName, MiddleName)"));
        }

        // В корне точки (section/{ID})?
        $inRootShbfLk = $isShbfOrLk
            && count($relativePartsNorm) === 1;

        // Читаем HTML-листинг текущей папки (с анти-кэшем)
        $links = [];
        if (!$storageUnavailable) {
            $links = $this->fetchHrefs("{$httpBaseUrl}/{$current}?_=" . time());
        }

        $dirs = $photos = [];
        foreach ($links as $href) {
            if ($href === '../') continue;

            if (str_ends_with($href, '/')) {
                $dirs[] = rtrim($href, '/');
            } elseif (preg_match('/\.(jpe?g|png)$/i', $href)) {
                $photos[] = basename($href);
            }
        }

        $dirsNorm = array_values(array_filter(array_map(
            static fn (string $v): string => mb_strtolower(trim($v)),
            $dirs
        ), static fn (string $v): bool => $v !== ''));
        $rootSectionsMatchCurrent = count(array_intersect($rootSectionsNorm, $dirsNorm)) > 0;

        // ФИО для цифровых папок
        $numericDirs = array_filter($dirs, fn($v) => is_numeric($v));
        $fioList     = [];
        if (!empty($numericDirs)) {
            $rows = DB::table('mergedvisitcontrol')
                ->whereIn('idmergedvisitcontrol', $numericDirs)
                ->select('idmergedvisitcontrol', DB::raw("CONCAT_WS(' ', LastName, FirstName, MiddleName) AS fio"))
                ->get();
            foreach ($rows as $r) {
                $fioList[$r->idmergedvisitcontrol] = $r->fio ?: $r->idmergedvisitcontrol;
            }
        }

        // Карта "числовая папка" → "фото" из PhotosStaff (только в корне SHBF/LK)
        $staffMap = [];
        if ($inRootShbfLk && !$storageUnavailable) {
            $staffFiles = $this->fetchHrefs("{$httpBaseUrl}{$photosBasePath}?_=" . time());
            foreach ($staffFiles as $fn) {
                if (preg_match('/^(\d+)\.(jpe?g|png)$/i', $fn, $m)) {
                    $staffMap[$m[1]] = $fn;
                }
            }
        }

        // Собираем элементы (папки)
        $allItems = [];
        foreach ($dirs as $folder) {
            $photoUrl = null;
            if ($inRootShbfLk && is_numeric($folder)) {
                if (isset($staffMap[$folder])) {
                    $photoUrl = "{$httpBaseUrl}{$photosBasePath}{$staffMap[$folder]}";
                } else {
                    $photoUrl = $this->photosNotFoundUrl();
                }
            }
            $allItems[] = [
                'id'    => $folder,
                'name'  => is_numeric($folder) ? ($fioList[$folder] ?? $folder) : $folder,
                'photo' => $photoUrl,
                'path'  => "{$current}/{$folder}",
            ];
        }

        // Сортируем папки по имени
        usort($allItems, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        // --- сортируем ФОТО по дате (новые сверху) ---
        // Поддерживаем имена:
        //   in|out_DDMMYYYY_HHMMSS.*
        //   in|out_YYYYMMDD_HHMMSS.*
        // Если из имени вытащить дату не удалось — Last-Modified.
        $photos = array_values($photos); // переиндексация
        usort($photos, function(string $a, string $b) {
            $tsa = $this->extractTimestampFromName($a);
            $tsb = $this->extractTimestampFromName($b);

            if ($tsa === '' && $tsb === '') return strcasecmp($b, $a);
            if ($tsa === '') return 1;
            if ($tsb === '') return -1;

            return strcmp($tsb, $tsa);
        });

        $photosPerPage = 60;
        $photosPage = max(1, (int) $req->query('page', 1));
        $photosTotal = count($photos);
        $photos = new LengthAwarePaginator(
            array_slice($photos, ($photosPage - 1) * $photosPerPage, $photosPerPage),
            $photosTotal,
            $photosPerPage,
            $photosPage,
            ['path' => $req->url(), 'query' => $req->query()]
        );

        return view('gallery', [
            'base'                => $base,
            'dir'                 => $current,
            'items'               => $allItems,
            'photos'              => $photos,
            'httpBaseUrl'         => $httpBaseUrl,
            'rootSections'        => $rootSections,
            'rootSectionsNorm'    => $rootSectionsNorm,
            'rootSectionsMatchCurrent' => $rootSectionsMatchCurrent,
            'notFoundUrl'         => $this->photosNotFoundUrl(),
            'isShbfOrLk'          => $isShbfOrLk,
            'isShbfOrLkSubfolder' => $isShbfOrLkSubfolder,
            'inRootShbfLk'        => $inRootShbfLk,
            'personName'          => $personName,
            'storageUnavailable'  => $storageUnavailable,
            'storageUnavailableReason' => $storageUnavailableReason,
        ]);
    }

public function allPassages(Request $request)
{
    $from = trim((string) $request->query('from', ''));
    $to = trim((string) $request->query('to', ''));
    $q = trim((string) $request->query('q', ''));
    $photoType = strtolower(trim((string) $request->query('photo_type', 'all')));
    $photoSort = strtolower(trim((string) $request->query('photo_sort', 'date-desc')));
    $edge = strtolower(trim((string) $request->query('edge', 'newest')));

    if (!in_array($photoType, ['all', 'in', 'out'], true)) {
        $photoType = 'all';
    }

    if (!in_array($photoSort, ['date-desc', 'date-asc', 'az', 'za'], true)) {
        $photoSort = 'date-desc';
    }

    if (!in_array($edge, ['newest', 'oldest'], true)) {
        $edge = 'newest';
    }

    $httpBaseUrl = $this->photosHttpBase();

    $photosQuery = DB::table('passage as p')
        ->leftJoin('mergedvisitcontrol as mv', 'mv.idmergedvisitcontrol', '=', 'p.IdMergedVisitControl')
        ->leftJoin('pairdevices as pd', 'pd.Id', '=', 'p.IdPairDevices')
        ->leftJoin('location as l', 'l.IdLocation', '=', DB::raw('COALESCE(p.IdLocation, pd.Location)'))
        ->whereNotNull('p.CameraPhoto')
        ->where('p.CameraPhoto', '<>', '');

    if ($from !== '') {
        $photosQuery->where('p.AccessTime', '>=', strlen($from) === 10 ? ($from . ' 00:00:00') : $from);
    }

    if ($to !== '') {
        $photosQuery->where('p.AccessTime', '<=', strlen($to) === 10 ? ($to . ' 23:59:59') : $to);
    }

    if ($photoType !== 'all') {
        $photoNamePrefix = $photoType === 'in' ? 'in' : 'out';
        $accessTypeValues = $photoType === 'in'
            ? ['in', 'вход', '1']
            : ['out', 'выход', '2'];

        $photosQuery->where(function ($where) use ($photoNamePrefix, $accessTypeValues): void {
            $where->whereRaw("LOWER(TRIM(COALESCE(p.AccessType, ''))) IN (?, ?, ?)", $accessTypeValues)
                ->orWhereRaw("LOWER(p.CameraPhoto) REGEXP ?", ['(^|/)' . $photoNamePrefix . '_']);
        });
    }

    if ($q !== '') {
        $qLike = '%' . $q . '%';
        $photosQuery->where(function ($where) use ($q, $qLike): void {
            $where->whereRaw("CONCAT_WS(' ', mv.LastName, mv.FirstName, mv.MiddleName) LIKE ?", [$qLike])
                ->orWhere('p.CameraPhoto', 'like', $qLike)
                ->orWhereRaw("COALESCE(NULLIF(TRIM(l.LocationName), ''), NULLIF(TRIM(pd.Name), '')) LIKE ?", [$qLike]);

            if (ctype_digit($q)) {
                $where->orWhere('p.IdMergedVisitControl', (int) $q);
            }
        });
    }

    $photos = $photosQuery
        ->select([
            'p.IdPassage',
            'p.IdMergedVisitControl as PersonId',
            'p.AccessTime',
            'p.AccessType',
            'p.CameraPhoto',
            DB::raw("CONCAT_WS(' ', mv.LastName, mv.FirstName, mv.MiddleName) as PersonFio"),
            DB::raw("COALESCE(NULLIF(TRIM(l.LocationName), ''), NULLIF(TRIM(pd.Name), '')) as Point"),
        ]);

    // Курсорная пагинация не вычисляет COUNT(*) и не использует OFFSET.
    // IdPassage дополняет время прохода, поэтому порядок остаётся однозначным.
    $direction = $edge === 'oldest' ? 'asc' : 'desc';
    $photos = $photos
        ->orderBy('p.AccessTime', $direction)
        ->orderBy('p.IdPassage', $direction)
        ->cursorPaginate(60, ['*'], 'cursor')
        ->appends($request->except('cursor'));

    // В обоих крайних режимах карточки читаются привычно: новые выше старых.
    if ($edge === 'oldest') {
        $photos->setCollection($photos->getCollection()->reverse()->values());
    }

    return view('all', [
        'photos' => $photos,
        'from' => $from,
        'to' => $to,
        'q' => $q,
        'photoType' => $photoType,
        'photoSort' => $photoSort,
        'edge' => $edge,
        'httpBaseUrl' => $httpBaseUrl,
        'notFoundUrl' => $this->photosNotFoundUrl(),
    ]);
}

    /**
     * Извлекает href="..." из HTML-листинга.
     */
    protected function fetchHrefs(string $url): array
    {
        try {
            $timeout = 6;
            $retryTimes = 2;
            $retrySleep = 200;

            $response = Http::withHeaders([
                        'Cache-Control' => 'no-cache',
                        'Pragma'        => 'no-cache',
                    ])
                    ->timeout($timeout)
                    ->retry($retryTimes, $retrySleep)
                    ->get($url);

            if (!$response->successful()) {
                \Log::warning("HTTP fetch failed for {$url}: status {$response->status()}");

                $this->notifyError('warning', 'Ошибка HTTP-запроса к фото-хранилищу', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);
                return [];
            }

            $html = (string) $response->body();

            if (preg_match_all('/href="([^"]+)"/i', $html, $m)) {
                return $m[1];
            }
        } catch (\Exception $e) {
            \Log::warning("HTTP fetch failed for {$url}: {$e->getMessage()}");

            $this->notifyError('error', 'Исключение при HTTP-запросе к фото-хранилищу', [
                'url' => $url,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
        return [];
    }

    /**
     * Пытается извлечь временную метку из имени файла.
     * Поддерживаются два формата: in|out_YYYYMMDD_HHMMSS.* и in|out_DDMMYYYY_HHMMSS.*
     * Возвращает строку в формате YYYYMMDDHHMMSS либо null, если дата не распознана.
     */
    private function extractTimestampFromName(string $name): ?string
    {
        // Ищем 8 цифр (дата) и 6 цифр (время) после префикса in_/out_
        if (!preg_match('/^(?:in|out)_(\d{8})_(\d{6})/i', $name, $m)) {
            return null;
        }

        $datePart = $m[1];
        $timePart = $m[2];

        // Попытка интерпретировать как YYYYMMDD
        $year  = (int)substr($datePart, 0, 4);
        $month = (int)substr($datePart, 4, 2);
        $day   = (int)substr($datePart, 6, 2);

        if (!checkdate($month, $day, $year)) {
            // Пробуем DDMMYYYY
            $day   = (int)substr($datePart, 0, 2);
            $month = (int)substr($datePart, 2, 2);
            $year  = (int)substr($datePart, 4, 4);
            if (!checkdate($month, $day, $year)) {
                return null;
            }
        }

        $hour   = (int)substr($timePart, 0, 2);
        $minute = (int)substr($timePart, 2, 2);
        $second = (int)substr($timePart, 4, 2);

        return sprintf('%04d%02d%02d%02d%02d%02d', $year, $month, $day, $hour, $minute, $second);
    }

    /**
     * HEAD-запросом берёт Last-Modified и возвращает 'YYYYMMDDHHMMSS'.
     * Если недоступно — null.
     */
    private function getLastModifiedTimestamp(string $baseUrl, string $current, string $name): ?string
    {
        try {
            $headTimeout = 4;

            $url = rtrim($baseUrl, '/') . '/' . trim($current, '/') . '/' . ltrim($name, '/');

            $resp = Http::withHeaders([
                        'Cache-Control' => 'no-cache',
                        'Pragma'        => 'no-cache',
                    ])
                    ->timeout($headTimeout)
                    ->head($url);

            if (!$resp->successful()) {
                return null;
            }

            $lm = $resp->header('Last-Modified');
            if (!$lm) {
                return null;
            }

            $dt = Carbon::parse($lm);
            return $dt->format('YmdHis');
        } catch (\Throwable $e) {
            \Log::info("getLastModifiedTimestamp fail for {$name}: {$e->getMessage()}");

            $this->notifyError('warning', 'Не удалось получить Last-Modified для фото', [
                'name' => $name,
                'base_url' => $baseUrl,
                'current' => $current,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * Проверка существования файла методом HEAD.
     */
    private function photoExists(string $url): bool
    {
        try {
            $response = Http::head($url);
            return $response->successful();
        } catch (\Exception $e) {
            \Log::error("photoExists error: {$e->getMessage()} for URL {$url}");

            $this->notifyError('error', 'Ошибка проверки существования фото', [
                'url' => $url,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            return false;
        }
    }

    private function galleryRootDir(): string
    {
        $value = trim((string) config('app.gallery_root_dir', ''), '/');
        if ($value !== '') {
            return $value;
        }

        return trim((string) config('app.gallery_root_dir_default', ''), '/');
    }

    /** @return array<int, string> */
    private function galleryRootSections(): array
    {
        try {
            $rows = DB::table('pairdevices as pd')
                ->leftJoin('location as l', 'l.IdLocation', '=', 'pd.Location')
                ->where('pd.IsActive', 1)
                ->selectRaw("COALESCE(NULLIF(TRIM(l.LocationName), ''), NULLIF(TRIM(pd.Name), '')) as section_name")
                ->get();

            return collect($rows)
                ->map(static fn ($r): string => trim((string) ($r->section_name ?? '')))
                ->filter(static fn (string $v): bool => $v !== '')
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function photosHttpBase(): string
    {
        $rawHost = trim((string) config('filesystems.disks.ftp.host', ''));
        $scheme = trim((string) config('app.photos_http_scheme', ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            $scheme = '';
        }

        if ($rawHost === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $rawHost)) {
            return rtrim($rawHost, '/');
        }

        if ($scheme === '') {
            $appUrl = trim((string) config('app.url', ''));
            if (preg_match('~^https?://~i', $appUrl)) {
                $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: '';
            }
        }

        if ($scheme === '') {
            $scheme = 'http';
        }

        return $scheme . '://' . rtrim($rawHost, '/');
    }

    /**
     * @return array{available: bool, reason: string, base_url: string, status: int|null}
     */
    private function photoStorageStatus(): array
    {
        $baseUrl = $this->photosHttpBase();
        if ($baseUrl === '') {
            return [
                'available' => false,
                'reason' => 'empty_photo_storage_host',
                'base_url' => '',
                'status' => null,
            ];
        }

        $probeUrl = rtrim($baseUrl, '/') . '/?_probe=' . time();

        try {
            $response = Http::timeout(4)
                ->retry(1, 200)
                ->head($probeUrl);

            $status = $response->status();
            $reachableStatuses = [301, 302, 303, 307, 308, 401, 403, 404, 405];

            if ($response->successful() || in_array($status, $reachableStatuses, true)) {
                return [
                    'available' => true,
                    'reason' => '',
                    'base_url' => $baseUrl,
                    'status' => $status,
                ];
            }

            \Log::warning("Photo storage probe failed for {$probeUrl}: status {$status}");

            $this->notifyError('warning', 'Проверка доступности фото-хранилища вернула ошибочный статус', [
                'probe_url' => $probeUrl,
                'status' => $status,
            ]);

            return [
                'available' => false,
                'reason' => 'photo_storage_probe_http_' . $status,
                'base_url' => $baseUrl,
                'status' => $status,
            ];
        } catch (\Throwable $e) {
            \Log::warning("Photo storage probe exception for {$probeUrl}: {$e->getMessage()}");

            $this->notifyError('error', 'Исключение при проверке доступности фото-хранилища', [
                'probe_url' => $probeUrl,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return [
                'available' => false,
                'reason' => 'photo_storage_probe_exception',
                'base_url' => $baseUrl,
                'status' => null,
            ];
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function notifyError(string $level, string $message, array $context = []): void
    {
        app(ErrorNotificationService::class)->log($level, $message, $context);
    }

    private function photosStaffPath(): string
    {
        $value = trim((string) config('app.photos_staff_path', ''), '/');
        if ($value === '') {
            $value = trim((string) config('app.photos_staff_path_default', ''), '/');
        }

        if ($value === '') {
            return '/';
        }

        return '/' . $value . '/';
    }

    private function photosNotFoundUrl(): string
    {
        $custom = trim((string) config('app.photos_not_found_path', ''), " \t\n\r\0\x0B\"");
        $baseUrl = rtrim($this->photosHttpBase(), '/');

        if ($custom !== '') {
            if (preg_match('~^https?://~i', $custom)) {
                return $custom;
            }

            if ($baseUrl !== '') {
                return $baseUrl . '/' . ltrim($custom, '/');
            }

            return self::TRANSPARENT_PIXEL_DATA_URI;
        }

        if ($baseUrl === '') {
            return self::TRANSPARENT_PIXEL_DATA_URI;
        }

        return $baseUrl . $this->photosStaffPath() . 'NotFound.jpg';
    }
}
