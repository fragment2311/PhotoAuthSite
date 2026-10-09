<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\UserVisibleException;
use App\Http\Controllers\Controller;
use App\Services\BootstrapConfigService;
use App\Services\LicenseService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\AppSettingsFileStore;
use RuntimeException;
use Throwable;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SettingsController extends Controller
{
    private const TABLE = 'app_settings';

public function index(
    LicenseService $licenseService,
    AppSettingsFileStore $store
) {
    return $this->renderSettingsPage($licenseService, $store, false, []);
}

    public function setupIndex(
        Request $request,
        LicenseService $licenseService,
        AppSettingsFileStore $store,
        BootstrapConfigService $bootstrapConfig
    ) {
        if (!$this->hasSetupAccess($request, $bootstrapConfig)) {
            abort(403);
        }

        return $this->renderSettingsPage(
            $licenseService,
            $store,
            true,
            $bootstrapConfig->missingCriticalFields()
        );
    }

    public function setupSave(
        Request $request,
        AppSettingsFileStore $store,
        BootstrapConfigService $bootstrapConfig
    ) {
        if (!$this->hasSetupAccess($request, $bootstrapConfig)) {
            abort(403);
        }

        try {
            $this->validateAndSaveSettings($request, $store);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new UserVisibleException(
                'Не удалось сохранить настройки. Проверьте корректность введённых данных и доступ к файлам.',
                null,
                422,
                $e
            );
        }

        return back()->with('ok', 'Настройки сохранены');
    }

    public function setupAuth(Request $request, BootstrapConfigService $bootstrapConfig)
    {
        $request->session()->forget('setup_access_granted');

        return response()->view('admin.settings.setup_auth', [
            'masterEnabled' => $bootstrapConfig->hasMasterPassword(),
            'masterLogin' => $bootstrapConfig->getMasterLogin(),
        ]);
    }

    public function setupAuthSubmit(Request $request, BootstrapConfigService $bootstrapConfig)
    {
        if ($this->hasSetupAccess($request, $bootstrapConfig)) {
            return redirect()->route('setup.settings');
        }

        $login = trim((string) $request->input('login', ''));
        $password = (string) $request->input('password', '');

        if ($password === '') {
            return back()->withErrors(['password' => 'Введите пароль'])->withInput();
        }

        if ($bootstrapConfig->verifyMasterCredentials($login, $password)) {
            $request->session()->put('setup_access_granted', true);
            return redirect()->route('setup.settings');
        }

        if ($login !== '' && $this->tryAdminAuth($login, $password)) {
            $request->session()->put('setup_access_granted', true);
            return redirect()->route('setup.settings');
        }

        return back()
            ->withErrors(['login' => 'Неверный логин или пароль'])
            ->withInput();
    }

    private function renderSettingsPage(
        LicenseService $licenseService,
        AppSettingsFileStore $store,
        bool $setupMode,
        array $missingCritical
    ) {
    $s = $store->loadDecrypted();
        // Лицензия: статус + "сколько осталось" с учетом grace-периода.
        $tz = (string) config('license.timezone', 'UTC');
        $licensePath = (string) config('license.license_path', storage_path('app/license/license.lic'));

        $status = $licenseService->getStatus();      // подпись, структура
        $access = $licenseService->reportAccess();   // доступ с учетом grace

        $exists = is_file($licensePath);

        $license = [
            'exists'         => $exists,
            'valid'          => (bool)($status['valid'] ?? false),
            'client_id'      => $status['client_id'] ?? null,
            'valid_until'    => $status['valid_until'] ?? null,
            'remaining_human'=> null,
            'reason'         => $status['reason'] ?? null,
            'is_grace'       => false,
            'is_grace_over'  => false,
            'expires_at'     => null,
            'grace_until'    => null,
        ];

        if ($license['valid'] && !empty($license['valid_until'])) {
            try {
                $validUntil = (string) $license['valid_until'];
                $expiresAt  = CarbonImmutable::createFromFormat('Y-m-d', $validUntil, $tz)->endOfDay();

                $graceDays  = max(0, (int) config('license.grace_days', 7));
                $graceUntil = $expiresAt->addDays($graceDays);
                $now        = CarbonImmutable::now($tz);

                $license['expires_at']  = $expiresAt->toDateTimeString();
                $license['grace_until'] = $graceUntil->toDateTimeString();

                if ($now->lessThanOrEqualTo($expiresAt)) {
                    // Обычный режим: считаем до конца лицензии.
                    $secondsLeft = $now->diffInSeconds($expiresAt, false);
                    $license['remaining_human'] = $this->humanizeSeconds(max(0, $secondsLeft));
                } elseif ($now->lessThanOrEqualTo($graceUntil)) {
                    // Grace-режим: считаем до конца grace.
                    $secondsLeft = $now->diffInSeconds($graceUntil, false);
                    $license['remaining_human'] = $this->humanizeSeconds(max(0, $secondsLeft));
                    $license['is_grace'] = true;
                    $license['reason']   = 'grace_period';
                } else {
                    // Grace закончился.
                    $license['remaining_human'] = '0 мин.';
                    $license['is_grace_over']   = true;
                    $license['reason']          = 'license_expired_grace_over';
                }
            } catch (\Throwable $e) {
                $license['valid']  = false;
                $license['reason'] = 'valid_until_parse_failed';
            }
        }

        // Если reportAccess говорит, что grace уже закончился и доступ запрещен — уважаем это.
        if (($access['allowed_view'] ?? true) === false
            && ($access['reason'] ?? '') === 'license_expired_grace_over') {

            $license['is_grace_over'] = true;
            $license['reason']        = 'license_expired_grace_over';
        }

        return view('admin.settings.index', [
            's'       => $s,
            'license' => $license,
            'setupMode' => $setupMode,
            'missingCritical' => $missingCritical,
        ]);
    }

public function save(Request $request, AppSettingsFileStore $store)
{
    try {
        $this->validateAndSaveSettings($request, $store);
    } catch (ValidationException $e) {
        throw $e;
    } catch (Throwable $e) {
        throw new UserVisibleException(
            'Не удалось сохранить настройки. Проверьте корректность введённых данных и доступ к файлам.',
            null,
            422,
            $e
        );
    }

    return back()->with('ok', 'Настройки сохранены');
}

    public function downloadLicenseLog(): BinaryFileResponse
    {
        $path = (string) config('license.log_path', storage_path('logs/license.log'));

        if (!is_file($path) || !is_readable($path)) {
            abort(404, 'Файл лога лицензии не найден');
        }

        return response()->download($path, 'license.log', [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    private function validateAndSaveSettings(Request $request, AppSettingsFileStore $store): void
{
    $existing = $store->loadDecrypted();

    $data = $request->validate([
        // Лицензия
        'license_client_id' => 'nullable|string|max:255',

        // DB
        'db_host' => 'nullable|string',
        'db_port' => 'nullable|string',
        'db_name' => 'nullable|string',
        'db_user' => 'nullable|string',
        'db_pass' => 'nullable|string',

        // Галерея/HTTP
        'gallery_root_dir' => 'nullable|string|max:255',
        'photos_staff_path' => 'nullable|string|max:255',
        'photos_http_scheme' => 'nullable|in:http,https',
        'photos_not_found_upload' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,bmp,svg|max:4096',

        // Фото-хранилище
        'photo_storage_type' => 'nullable|in:share,ftps',

        'photo_share_path'   => 'nullable|string',
        'photo_share_subdir' => 'nullable|string',

        'photo_ftps_host'    => 'nullable|string',
        'photo_ftps_port'    => 'nullable|string',
        'photo_ftps_user'    => 'nullable|string',
        'photo_ftps_pass'    => 'nullable|string',
        'photo_ftps_dir'     => 'nullable|string',
        'photo_ftps_passive' => 'nullable|in:0,1',
        'photo_ftps_ssl'     => 'nullable|in:0,1',
        'photo_ftps_allow_self_signed' => 'nullable|in:0,1',
        'photo_ftps_server_cert_thumbprint' => [
            'nullable',
            'string',
            'max:255',
        ],

        // Старые ключи (оставил как у тебя)
        'photo_disk' => 'nullable|string',
        'photo_path' => 'nullable|string',

        // setup master password
        'setup_master_login' => 'nullable|string|min:3|max:200',
        'setup_master_password' => 'nullable|string|min:6|max:255',
        'setup_master_password_confirmation' => 'nullable|string',

    ]);

    $masterLogin = trim((string) $request->input('setup_master_login', ''));
    if ($masterLogin !== '') {
        $data['setup_master_login'] = $masterLogin;
    } else {
        $existingLogin = trim((string) ($existing['setup_master_login'] ?? ''));
        if ($existingLogin !== '') {
            $data['setup_master_login'] = $existingLogin;
        }
    }

    $masterPassword = (string) $request->input('setup_master_password', '');
    if ($masterPassword !== '') {
        $confirmed = (string) $request->input('setup_master_password_confirmation', '');
        if ($masterPassword !== $confirmed) {
            throw ValidationException::withMessages([
                'setup_master_password_confirmation' => 'Подтверждение мастер-пароля не совпадает',
            ]);
        }

        $data['setup_master_password_hash'] = password_hash($masterPassword, PASSWORD_DEFAULT);
    } else {
        $existingHash = trim((string) ($existing['setup_master_password_hash'] ?? ''));
        if ($existingHash !== '') {
            $data['setup_master_password_hash'] = $existingHash;
        }
    }

    unset($data['setup_master_password'], $data['setup_master_password_confirmation']);

    $uploadedNotFound = $request->file('photos_not_found_upload');
    if ($uploadedNotFound && $uploadedNotFound->isValid()) {
        $dstDir = public_path('uploads/placeholders');
        if (!is_dir($dstDir)) {
            @mkdir($dstDir, 0775, true);
        }

        $ext = strtolower((string) $uploadedNotFound->getClientOriginalExtension());
        if ($ext === '') {
            $ext = 'png';
        }

        $name = 'not-found-' . date('YmdHis') . '-' . uniqid('', true) . '.' . $ext;
        $uploadedNotFound->move($dstDir, $name);
        $data['photos_not_found_path'] = '/uploads/placeholders/' . $name;
    }

    unset($data['photos_not_found_upload']);

    // важно: пустые поля паролей НЕ затирают существующие
    $store->save($data, keepEncryptedSecrets: true);
}

    public function devicePairsApiToken()
    {
        $token = (string) env('API_TOKEN', '');

        if ($token === '') {
            return response()->json([
                'ok' => false,
                'message' => 'API_TOKEN не задан',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'token' => $token,
        ]);
    }

    public function generateDevicePairsApiToken()
    {
        try {
            $token = $this->generateAndPersistDevicePairsApiToken();

            return response()->json([
                'ok' => true,
                'token' => $token,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Не удалось сгенерировать токен',
            ], 500);
        }
    }

    public function setupDevicePairsApiToken(Request $request, BootstrapConfigService $bootstrapConfig)
    {
        if (!$this->hasSetupAccess($request, $bootstrapConfig)) {
            return response()->json([
                'ok' => false,
                'message' => 'Доступ запрещён',
            ], 403);
        }

        return $this->devicePairsApiToken();
    }

    public function setupGenerateDevicePairsApiToken(Request $request, BootstrapConfigService $bootstrapConfig)
    {
        if (!$this->hasSetupAccess($request, $bootstrapConfig)) {
            return response()->json([
                'ok' => false,
                'message' => 'Доступ запрещён',
            ], 403);
        }

        return $this->generateDevicePairsApiToken();
    }

    public function uploadLicense(Request $request, LicenseService $licenseService)
    {
        $request->validate([
            'license_file' => ['required', 'file', 'max:1024'], // при желании поправь лимит
        ]);

        $file = $request->file('license_file');

        // Чуть подстрахуемся по имени.
        $orig = (string) $file->getClientOriginalName();
        if ($orig !== '' && mb_strtolower($orig) !== 'license.lic') {
            return back()->with('err', 'Нужен файл строго с именем license.lic');
        }

        $dstPath = (string) config('license.license_path', storage_path('app/license/license.lic'));
        $dstDir  = dirname($dstPath);

        if (!is_dir($dstDir)) {
            @mkdir($dstDir, 0775, true);
        }

        // Перезаписываем.
        $file->move($dstDir, basename($dstPath));

        // Сброс кеша, чтобы новая лицензия сразу подхватилась.
        $licenseService->clearCache();

        $status = $licenseService->getStatus();
        if (!($status['valid'] ?? false)) {
            @unlink($dstPath);
            $licenseService->clearCache();

            return back()->with('err', 'Лицензия не принята: ' . ($status['reason'] ?? 'license_invalid'));
        }

        return back()->with('ok', 'Лицензия загружена и применена.');
    }

    private function humanizeSeconds(int $seconds): string
    {
        if ($seconds < 0) {
            $seconds = 0;
        }

        $secondsInDay  = 86400;
        $secondsInHour = 3600;
        $secondsInMin  = 60;
        $yearDays      = 365;

        $daysTotal = intdiv($seconds, $secondsInDay);
        $seconds  -= $daysTotal * $secondsInDay;

        $hours = intdiv($seconds, $secondsInHour);
        $seconds -= $hours * $secondsInHour;

        $mins = intdiv($seconds, $secondsInMin);

        $years = intdiv($daysTotal, $yearDays);
        $days  = $daysTotal % $yearDays;

        $parts = [];

        if ($years > 0) {
            $parts[] = $years . ' г.';
        }

        // если лет нет — показываем все дни (как у тебя в примере 676 дн. …)
        if ($days > 0 || $years === 0) {
            $parts[] = $days . ' дн.';
        }

        if ($hours > 0 || $years > 0 || $days > 0) {
            $parts[] = $hours . ' ч.';
        }

        $parts[] = $mins . ' мин.';

        return implode(' ', $parts);
    }

    private function generateAndPersistDevicePairsApiToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->writeEnvValue('API_TOKEN', $token);

        putenv('API_TOKEN=' . $token);
        $_ENV['API_TOKEN'] = $token;
        $_SERVER['API_TOKEN'] = $token;

        return $token;
    }

    private function writeEnvValue(string $key, string $value): void
    {
        $envPath = base_path('.env');
        $line = $key . '=' . $value;

        if (!is_file($envPath)) {
            if (@file_put_contents($envPath, $line . PHP_EOL) === false) {
                throw new RuntimeException('env_create_failed');
            }
            return;
        }

        $contents = (string) @file_get_contents($envPath);
        if ($contents === '') {
            if (@file_put_contents($envPath, $line . PHP_EOL) === false) {
                throw new RuntimeException('env_write_failed');
            }
            return;
        }

        $pattern = '/^\s*' . preg_quote($key, '/') . '\s*=.*$/m';

        if (preg_match($pattern, $contents) === 1) {
            $updated = preg_replace($pattern, $line, $contents);
            if ($updated === null || @file_put_contents($envPath, $updated) === false) {
                throw new RuntimeException('env_update_failed');
            }
            return;
        }

        $suffix = str_ends_with($contents, "\n") ? '' : PHP_EOL;
        if (@file_put_contents($envPath, $contents . $suffix . $line . PHP_EOL) === false) {
            throw new RuntimeException('env_append_failed');
        }
    }

    private function hasSetupAccess(Request $request, BootstrapConfigService $bootstrapConfig): bool
    {
        if ((bool) $request->session()->get('setup_access_granted', false) === true) {
            return true;
        }

        if ($bootstrapConfig->hasMasterPassword()) {
            return false;
        }

        $provided = trim((string) $request->query('setup_key', ''));
        if ($provided === '') {
            $provided = trim((string) $request->header('X-SETUP-KEY', ''));
        }

        if ($bootstrapConfig->isValidSetupKey($provided)) {
            $request->session()->put('setup_access_granted', true);
            return true;
        }

        return false;
    }

    private function tryAdminAuth(string $login, string $password): bool
    {
        $credentials = [
            'Login' => $login,
            'password' => $password,
        ];

        if (!Auth::attempt($credentials)) {
            return false;
        }

        if (!$this->isCurrentUserAdmin()) {
            Auth::logout();
            return false;
        }

        return true;
    }

    private function isCurrentUserAdmin(): bool
    {
        try {
            $user = Auth::user();
            $role = trim((string) ($user->role->NameRole ?? ''));

            return mb_strtolower($role) === 'администратор';
        } catch (\Throwable $e) {
            return false;
        }
    }
}
