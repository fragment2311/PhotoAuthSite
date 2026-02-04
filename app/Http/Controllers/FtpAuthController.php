<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Models\Webuser;
use App\Models\Role;
use Carbon\Carbon;

class FtpAuthController extends Controller
{
    // === Регистрация (форма) ===
    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('gallery', ['dir' => 'entrance_photos']);
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
        if (Auth::check()) {
            return redirect()->route('gallery', ['dir' => 'entrance_photos'])
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
        return redirect()->route('gallery', ['dir' => 'entrance_photos']);
    }

    // === Логин (действие) ===
    public function login(Request $request)
    {
        $user = Webuser::where('Login', $request->input('login'))->first();

        if ($user && ! $user->IsActive) {
            return back()
                ->withErrors(['login' => 'Ваш аккаунт заблокирован'])
                ->withInput();
        }

        $credentials = [
            'Login'    => $request->input('login'),
            'password' => $request->input('password'),
        ];
        if (Auth::attempt($credentials)) {
            return $this->redirectByRole();
        }

        return back()
            ->withErrors(['login' => 'Неверный логин или пароль'])
            ->withInput();
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
        $base           = 'entrance_photos';
        $current        = $dir ? trim($dir, '/') : $base;
        $httpBaseUrl    = 'http://192.168.9.134';
        $photosBasePath = '/entrance_photos/PhotosStaff/';

        // Разбиваем путь на части
        $parts = explode('/', $current);

        // Где мы? SHBF/LK и это подпапка?
        $isShbfOrLk = in_array('SHBF', $parts, true) || in_array('LK', $parts, true);
        $isShbfOrLkSubfolder = $isShbfOrLk && count($parts) > 2;

        // ФИО для шапки
        $personId = collect($parts)->reverse()->first(fn($seg) => is_numeric($seg));
        $personName = null;
        if ($personId) {
            $personName = DB::table('mergedvisitcontrol')
                ->where('idmergedvisitcontrol', $personId)
                ->value(DB::raw("CONCAT_WS(' ', LastName, FirstName, MiddleName)"));
        }

        // В корне SHBF/{ID} или LK/{ID}?
        $inRootShbfLk = $isShbfOrLk && count($parts) === 2;

        // Читаем HTML-листинг текущей папки (с анти-кэшем)
        $links = $this->fetchHrefs("{$httpBaseUrl}/{$current}?_=" . time());

        $dirs = $photos = [];
        foreach ($links as $href) {
            if ($href === '../') continue;

            if (str_ends_with($href, '/')) {
                $dirs[] = rtrim($href, '/');
            } elseif (preg_match('/\.(jpe?g|png)$/i', $href)) {
                $photos[] = basename($href);
            }
        }

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
        if ($inRootShbfLk) {
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
                $fileName = $staffMap[$folder] ?? 'NotFound.png';
                $photoUrl = "{$httpBaseUrl}{$photosBasePath}{$fileName}";
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
        usort($photos, function(string $a, string $b) use ($httpBaseUrl, $current) {
            $tsa = $this->extractTimestampFromName($a);
            $tsb = $this->extractTimestampFromName($b);

            if (!$tsa) $tsa = $this->getLastModifiedTimestamp($httpBaseUrl, $current, $a) ?? '';
            if (!$tsb) $tsb = $this->getLastModifiedTimestamp($httpBaseUrl, $current, $b) ?? '';

            // Пустые в конец
            if ($tsa === '' && $tsb === '') return strcasecmp($b, $a); // стабильность
            if ($tsa === '') return 1;
            if ($tsb === '') return -1;

            // Сравнение YYYYMMDDHHMMSS как строк
            return strcmp($tsb, $tsa);
        });

        return view('gallery', [
            'base'                => $base,
            'dir'                 => $current,
            'items'               => $allItems,
            'photos'              => $photos,
            'httpBaseUrl'         => $httpBaseUrl,
            'isShbfOrLk'          => $isShbfOrLk,
            'isShbfOrLkSubfolder' => $isShbfOrLkSubfolder,
            'inRootShbfLk'        => $inRootShbfLk,
            'personName'          => $personName,
        ]);
    }

    public function allPassages(Request $request)
{
    $from = $request->query('from');
    $to   = $request->query('to');

    if (!$from && !$to) {
        $from = now()->startOfMonth()->toDateString();
        $to   = now()->endOfMonth()->toDateString();
    }

    $photos = DB::table('passage as p')
->leftJoin('controllers as c', 'c.Id', '=', 'p.IdLocation')
        ->whereNotNull('p.CameraPhoto')
        ->where('p.CameraPhoto', '<>', '')
        ->whereBetween('p.AccessTime', [
            $from . ' 00:00:00',
            $to   . ' 23:59:59',
        ])
        ->orderByDesc('p.AccessTime')
        ->select([
            'p.IdPassage',
            'p.AccessTime',
            'p.CameraPhoto',
            DB::raw('c.Location as Point'),
        ])
        ->get();

    // Вьюха: resources/views/all.blade.php
$httpBaseUrl = 'http://192.168.9.134';

return view('all', [
    'photos' => $photos,
    'httpBaseUrl' => $httpBaseUrl,
]);
}


    /**
     * Извлекает href="..." из HTML-листинга.
     */
    protected function fetchHrefs(string $url): array
    {
        try {
            $html = Http::withHeaders([
                        'Cache-Control' => 'no-cache',
                        'Pragma'        => 'no-cache',
                    ])
                    ->timeout(6)
                    ->retry(2, 200)
                    ->get($url)
                    ->body();

            if (preg_match_all('/href="([^"]+)"/i', $html, $m)) {
                return $m[1];
            }
        } catch (\Exception $e) {
            \Log::warning("HTTP fetch failed for {$url}: {$e->getMessage()}");
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
            $url = rtrim($baseUrl, '/') . '/' . trim($current, '/') . '/' . ltrim($name, '/');

            $resp = Http::withHeaders([
                        'Cache-Control' => 'no-cache',
                        'Pragma'        => 'no-cache',
                    ])
                    ->timeout(4)
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
            return false;
        }
    }
}
