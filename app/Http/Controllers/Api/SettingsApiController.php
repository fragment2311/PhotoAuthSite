<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Webuser;
use App\Services\AppSettingsFileStore;
use App\Support\ApiErrorFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsApiController extends Controller
{
    public function show(AppSettingsFileStore $store): JsonResponse
    {
        $s = $store->loadDecrypted();

        $dbHost = trim((string) ($s['db_host'] ?? ''));
        $dbUser = trim((string) ($s['db_user'] ?? ''));
        $dbPass = (string) ($s['db_pass'] ?? '');
        $photoFtpsPass = (string) ($s['photo_ftps_pass'] ?? '');
        $dahuaFtpsPass = (string) ($s['dahua_sdk_ftps_pass'] ?? '');
        if ($dahuaFtpsPass === '') {
            $dahuaFtpsPass = $photoFtpsPass;
        }

        $photoServerCertThumbprint = $s['photo_ftps_server_cert_thumbprint'] ?? null;
        if (is_string($photoServerCertThumbprint)) {
            $photoServerCertThumbprint = trim($photoServerCertThumbprint);
            if ($photoServerCertThumbprint === '') {
                $photoServerCertThumbprint = null;
            }
        }

        $photo = [
            'storage_type' => $s['photo_storage_type'] ?? null, // share|ftps
            'share_path' => $s['photo_share_path'] ?? null,
            'share_subdir' => $s['photo_share_subdir'] ?? null,
            // Адрес открытия фото может отличаться от FTPS-хоста.
            // При отсутствии отдельной настройки сохраняем совместимость с прежним URL.
            'public_http_base' => $this->resolvePhotoPublicHttpBase($s),
            'ftps' => [
                'host' => $s['photo_ftps_host'] ?? null,
                'port' => $s['photo_ftps_port'] ?? null,
                'user' => $s['photo_ftps_user'] ?? null,
                'pass' => $photoFtpsPass,
                'dir' => $s['photo_ftps_dir'] ?? null,
                'passive' => (int) ($s['photo_ftps_passive'] ?? 0),
                'ssl' => (int) ($s['photo_ftps_ssl'] ?? 1),
                'server_cert_thumbprint' => $photoServerCertThumbprint,
                'allow_self_signed' => (int) ($s['photo_ftps_allow_self_signed'] ?? 0),
                'has_password' => $photoFtpsPass !== '',
            ],
        ];

        $dahuaStorageType = (string) ($s['dahua_sdk_storage_type'] ?? ($photo['storage_type'] ?? 'local'));
        if (! in_array($dahuaStorageType, ['share', 'ftps', 'local'], true)) {
            $dahuaStorageType = 'local';
        }

        $dahuaFilesCsv = trim((string) ($s['dahua_sdk_files'] ?? ''));
        $dahuaFiles = array_values(array_filter(
            array_map('trim', explode(',', $dahuaFilesCsv)),
            static fn (string $item): bool => $item !== ''
        ));
        if ($dahuaFiles === []) {
            $dahuaFiles = ['dhnetsdk.dll', 'dhconfigsdk.dll'];
        }

        $dahuaLocalDir = trim((string) ($s['dahua_sdk_local_dir'] ?? ''));
        if ($dahuaLocalDir === '') {
            $dahuaLocalDir = '%ProgramData%\\MySkud\\sdk\\dahua';
        }

        $dahuaServerCertThumbprint = $s['dahua_sdk_ftps_server_cert_thumbprint'] ?? null;
        if (is_string($dahuaServerCertThumbprint)) {
            $dahuaServerCertThumbprint = trim($dahuaServerCertThumbprint);
            if ($dahuaServerCertThumbprint === '') {
                $dahuaServerCertThumbprint = null;
            }
        }

        if (! array_key_exists('dahua_sdk_ftps_server_cert_thumbprint', $s)) {
            $dahuaServerCertThumbprint = $photoServerCertThumbprint;
        }

        $dahuaAllowSelfSigned = array_key_exists('dahua_sdk_ftps_allow_self_signed', $s)
            ? (int) ($s['dahua_sdk_ftps_allow_self_signed'] ?? 0)
            : (int) ($s['photo_ftps_allow_self_signed'] ?? 0);

        $dahua = [
            'storage_type' => $dahuaStorageType,
            'share_path' => $s['dahua_sdk_share_path'] ?? null,
            'share_subdir' => $s['dahua_sdk_share_subdir'] ?? null,
            'local_dir' => $dahuaLocalDir,
            'files' => $dahuaFiles,
            'ftps' => [
                'host' => $s['dahua_sdk_ftps_host'] ?? null,
                'port' => (string) ($s['dahua_sdk_ftps_port'] ?? ($s['photo_ftps_port'] ?? (string) config('ftp.port', 21))),
                'user' => $s['dahua_sdk_ftps_user'] ?? null,
                'pass' => $dahuaFtpsPass,
                'dir' => $s['dahua_sdk_ftps_dir'] ?? null,
                'passive' => (int) ($s['dahua_sdk_ftps_passive'] ?? ($s['photo_ftps_passive'] ?? 0)),
                'ssl' => (int) ($s['dahua_sdk_ftps_ssl'] ?? ($s['photo_ftps_ssl'] ?? 1)),
                'server_cert_thumbprint' => $dahuaServerCertThumbprint,
                'allow_self_signed' => $dahuaAllowSelfSigned,
                'has_password' => $dahuaFtpsPass !== '',
            ],
        ];

        // Базовая валидация/нормализация для стабильного server-side контракта.
        if ($dahua['storage_type'] === 'share' && empty($dahua['share_path'])) {
            $dahua['share_path'] = $photo['share_path'];
        }

        if ($dahua['storage_type'] === 'ftps') {
            foreach (['host', 'user'] as $requiredField) {
                if (empty($dahua['ftps'][$requiredField])) {
                    $dahua['ftps'][$requiredField] = $photo['ftps'][$requiredField] ?? null;
                }
            }

            if (! ($dahua['ftps']['has_password'] ?? false) && ! empty($s['photo_ftps_pass'])) {
                $dahua['ftps']['has_password'] = true;
            }
        }

        return response()->json([
            'allow_error_reports' => 1,
            'db' => [
                'host' => $s['db_host'] ?? null,
                'port' => $s['db_port'] ?? null,
                'name' => $s['db_name'] ?? null,
                'user' => $s['db_user'] ?? null,
                'pass' => $dbPass,
                'has_password' => $dbPass !== '',
                'is_configured' => $dbHost !== '' && $dbUser !== '',
            ],
            'photo' => $photo,
            'dahua_sdk' => $dahua,
        ]);
    }

    /**
     * GET /api/settings/table
     * Чтение записи из таблицы settings.
     */
    public function tableIndex(Request $request): JsonResponse
    {
        $settings = AppSetting::query()
            ->orderByDesc('ChangedAt')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'count' => $settings->count(),
            'settings' => $settings->map(fn (AppSetting $item): array => $this->mapSetting($item))->values(),
        ]);
    }

    /**
     * PUT /api/settings/table
     * Создаёт новую ревизию в таблице settings с аудит-полями.
     */
    public function tableUpsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'min:1'],
            'ServerIp' => ['nullable', 'string', 'max:255'],
            'DbUser' => ['nullable', 'string', 'max:255'],
            'DbPassword' => ['nullable', 'string', 'max:255'],
            'TerminalIp' => ['nullable', 'string', 'max:255'],
            'TerminalUser' => ['nullable', 'string', 'max:255'],
            'TerminalPassword' => ['nullable', 'string', 'max:255'],
            'IPCam' => ['nullable', 'string', 'max:255'],
            'LoginCam' => ['nullable', 'string', 'max:255'],
            'PassCam' => ['nullable', 'string', 'max:255'],
            'idwebusers' => ['nullable', 'integer', 'min:1'],
        ]);

        $hasActorId = array_key_exists('idwebusers', $data);
        $idWebusers = $hasActorId ? (int) $data['idwebusers'] : null;

        if ($hasActorId && $idWebusers !== null) {
            $exists = Webuser::query()->where('Id', $idWebusers)->exists();
            if (! $exists) {
                return ApiErrorFormatter::response(
                    $request,
                    422,
                    'idwebusers_not_found',
                    'idwebusers not found',
                    ['errors' => ['idwebusers' => ['idwebusers not found']]]
                );
            }
        }

        $settingId = isset($data['id']) ? (int) $data['id'] : null;
        $baseSetting = $settingId
            ? AppSetting::query()->find($settingId)
            : AppSetting::query()
                ->orderByDesc('ChangedAt')
                ->orderByDesc('id')
                ->first();

        if ($settingId && ! $baseSetting) {
            return ApiErrorFormatter::response($request, 404, 'setting_not_found', 'setting not found');
        }

        $setting = new AppSetting;

        if ($baseSetting) {
            foreach (['ServerIp', 'DbUser', 'DbPassword', 'TerminalIp', 'TerminalUser', 'TerminalPassword', 'IPCam', 'LoginCam', 'PassCam'] as $field) {
                $setting->{$field} = $baseSetting->{$field};
            }

            $setting->IdWebusers = $baseSetting->IdWebusers;
        }

        foreach (['ServerIp', 'DbUser', 'DbPassword', 'TerminalIp', 'TerminalUser', 'TerminalPassword', 'IPCam', 'LoginCam', 'PassCam'] as $field) {
            if (array_key_exists($field, $data)) {
                $setting->{$field} = $this->normalizeSettingValue($data[$field]);
            }
        }

        if ($hasActorId) {
            $setting->IdWebusers = $idWebusers;
        }

        $setting->ChangedAt = now();
        $setting->save();

        return response()->json([
            'saved' => true,
            'setting' => $this->mapSetting($setting),
        ]);
    }

    private function normalizeSettingValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE);

        return $json === false ? null : $json;
    }

    private function mapSetting(AppSetting $setting): array
    {
        $changedAt = $setting->ChangedAt;

        return [
            'id' => (int) $setting->id,
            'ServerIp' => $setting->ServerIp,
            'DbUser' => $setting->DbUser,
            'DbPassword' => $setting->DbPassword,
            'TerminalIp' => $setting->TerminalIp,
            'TerminalUser' => $setting->TerminalUser,
            'TerminalPassword' => $setting->TerminalPassword,
            'IPCam' => $setting->IPCam,
            'LoginCam' => $setting->LoginCam,
            'PassCam' => $setting->PassCam,
            'idwebusers' => isset($setting->IdWebusers) ? (int) $setting->IdWebusers : null,
            'useredit' => isset($setting->IdWebusers) ? (int) $setting->IdWebusers : null,
            'changed_at' => $changedAt ? $changedAt->toDateTimeString() : null,
        ];
    }

    /** @param array<string, mixed> $settings */
    private function resolvePhotoPublicHttpBase(array $settings): ?string
    {
        $configured = trim((string) ($settings['photo_public_http_base'] ?? ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $fallback = trim((string) config('ftp.public_http_base', ''));

        return $fallback === '' ? null : rtrim($fallback, '/');
    }
}
