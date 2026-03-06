<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AppSettingsFileStore;
use Illuminate\Http\JsonResponse;

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
            'share_path'   => $s['photo_share_path'] ?? null,
            'share_subdir' => $s['photo_share_subdir'] ?? null,
            'ftps' => [
                'host'    => $s['photo_ftps_host'] ?? null,
                'port'    => $s['photo_ftps_port'] ?? null,
                'user'    => $s['photo_ftps_user'] ?? null,
                'pass'    => $photoFtpsPass,
                'dir'     => $s['photo_ftps_dir'] ?? null,
                'passive' => (int)($s['photo_ftps_passive'] ?? 0),
                'ssl'     => (int)($s['photo_ftps_ssl'] ?? 1),
                'server_cert_thumbprint' => $photoServerCertThumbprint,
                'allow_self_signed'      => (int)($s['photo_ftps_allow_self_signed'] ?? 0),
                'has_password' => $photoFtpsPass !== '',
            ],
        ];

        $dahuaStorageType = (string)($s['dahua_sdk_storage_type'] ?? ($photo['storage_type'] ?? 'local'));
        if (!in_array($dahuaStorageType, ['share', 'ftps', 'local'], true)) {
            $dahuaStorageType = 'local';
        }

        $dahuaFilesCsv = trim((string)($s['dahua_sdk_files'] ?? ''));
        $dahuaFiles = array_values(array_filter(
            array_map('trim', explode(',', $dahuaFilesCsv)),
            static fn (string $item): bool => $item !== ''
        ));
        if ($dahuaFiles === []) {
            $dahuaFiles = ['dhnetsdk.dll', 'dhconfigsdk.dll'];
        }

        $dahuaLocalDir = trim((string)($s['dahua_sdk_local_dir'] ?? ''));
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

        if (!array_key_exists('dahua_sdk_ftps_server_cert_thumbprint', $s)) {
            $dahuaServerCertThumbprint = $photoServerCertThumbprint;
        }

        $dahuaAllowSelfSigned = array_key_exists('dahua_sdk_ftps_allow_self_signed', $s)
            ? (int)($s['dahua_sdk_ftps_allow_self_signed'] ?? 0)
            : (int)($s['photo_ftps_allow_self_signed'] ?? 0);

        $dahua = [
            'storage_type' => $dahuaStorageType,
            'share_path'   => $s['dahua_sdk_share_path'] ?? null,
            'share_subdir' => $s['dahua_sdk_share_subdir'] ?? null,
            'local_dir'    => $dahuaLocalDir,
            'files'        => $dahuaFiles,
            'ftps' => [
                'host'                    => $s['dahua_sdk_ftps_host'] ?? null,
                'port'                    => (string)($s['dahua_sdk_ftps_port'] ?? ($s['photo_ftps_port'] ?? (string) config('ftp.port', 21))),
                'user'                    => $s['dahua_sdk_ftps_user'] ?? null,
                'pass'                    => $dahuaFtpsPass,
                'dir'                     => $s['dahua_sdk_ftps_dir'] ?? null,
                'passive'                 => (int)($s['dahua_sdk_ftps_passive'] ?? ($s['photo_ftps_passive'] ?? 0)),
                'ssl'                     => (int)($s['dahua_sdk_ftps_ssl'] ?? ($s['photo_ftps_ssl'] ?? 1)),
                'server_cert_thumbprint'  => $dahuaServerCertThumbprint,
                'allow_self_signed'       => $dahuaAllowSelfSigned,
                'has_password'            => $dahuaFtpsPass !== '',
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

            if (!($dahua['ftps']['has_password'] ?? false) && !empty($s['photo_ftps_pass'])) {
                $dahua['ftps']['has_password'] = true;
            }
        }

        return response()->json([
            'allow_error_reports' => (int)($s['allow_error_reports'] ?? 0),
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
}
