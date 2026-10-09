<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PairDevice;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Log;

class DevicePairApiController extends Controller
{
    /**
     * GET /api/device-pairs/active
     * Список активных пар устройств с кредами (формат для C#).
     */
    public function active(Request $request): JsonResponse
    {
        $format = $this->resolveRequestedFormat($request);
        $isV2 = $format === 'v2';
        $query = PairDevice::query()
            ->with(['members.controller', 'members.camera', 'locationRef'])
            ->where(function ($q): void {
                $q->where('IsActive', 1)
                    ->orWhereNull('IsActive');
            })
            ->orderBy('Id');

        $pairs = $query->get();

        $result = [];

        foreach ($pairs as $pair) {
            $pairId = (int) $pair->id;
            $pairName = (string) ($pair->Name ?? $pair->name ?? '');
            $base = [
                'pair_id' => $pairId,
                'id' => $pairId,
                'pair_name' => $pairName,
                'name' => $pairName,
                'is_active' => (bool) ($pair->IsActive ?? 0),
                'comment' => $pair->Comment ?? null,
                'location_id' => isset($pair->Location) ? (int) $pair->Location : null,
                'factory_code' => $this->resolveFactoryCode($pair),
            ];

            try {
                $devices = $this->collectDevicesForPair($pair);
                $v1Payload = $this->toV1Payload($base, $devices);

                $result[] = $isV2
                    ? $this->toV2Payload($base, $devices, $v1Payload)
                    : ($v1Payload + ['devices' => $devices]);
            } catch (\Throwable $e) {
                // Не раскрываем детали, но фиксируем в лог сервера
                Log::warning('DevicePairApiController: pair serialization failed', [
                    'pair_id' => $pair->id,
                    'error'   => $e->getMessage(),
                ]);

                // Чтобы C# явно увидел проблему конфигурации
                $fallback = [
                    'pair_id'      => $pairId,
                    'id'           => $pairId,
                    'pair_name'    => $pairName,
                    'name'         => $pairName,
                    'is_active'    => (bool) ($pair->IsActive ?? 0),
                    'warning'      => 'decryption_error',
                    'controller'   => null,
                    'entry_camera' => null,
                    'exit_camera'  => null,
                    'devices'      => [],
                ];

                $result[] = $isV2
                    ? ($fallback + [
                        'location_id' => $base['location_id'],
                        'comment' => $base['comment'],
                        'factory_code' => $base['factory_code'],
                    ])
                    : $fallback;
            }
        }

        return response()->json($result);
    }

    private function collectDevicesForPair(PairDevice $pair): array
    {
        $devices = [];

        foreach ($pair->members as $member) {
            if (!(bool) ($member->IsActive ?? 1)) {
                continue;
            }

            $memberId = (int) $member->Id;
            $sortOrder = (int) ($member->SortOrder ?? 100);
            $role = (string) ($member->Role ?? '');

            if ($member->controller) {
                $mapped = $this->mapDevice($member->controller, 'controller', 'controller', $memberId, $sortOrder);
                if ($mapped !== null) {
                    $devices[] = $mapped;
                }
            }

            if ($member->camera) {
                $cameraRole = in_array($role, ['entry_camera', 'exit_camera'], true)
                    ? $role
                    : 'entry_camera';
                $mapped = $this->mapDevice($member->camera, 'camera', $cameraRole, $memberId, $sortOrder);
                if ($mapped !== null) {
                    $devices[] = $mapped;
                }
            }
        }

        return $devices;
    }

    private function toV1Payload(array $base, array $devices): array
    {
        $controller = null;
        $entry = null;
        $exit = null;

        foreach ($devices as $dev) {
            $role = (string) ($dev['role'] ?? '');
            $type = (string) ($dev['type'] ?? '');

            if ($controller === null && ($role === 'controller' || $type === 'controller')) {
                $controller = $this->stripForV1($dev);
                continue;
            }

            if ($entry === null && ($role === 'entry' || $role === 'entry_camera')) {
                $entry = $this->stripForV1($dev);
                continue;
            }

            if ($exit === null && ($role === 'exit' || $role === 'exit_camera')) {
                $exit = $this->stripForV1($dev);
                continue;
            }
        }

        if ($entry === null) {
            foreach ($devices as $dev) {
                if (($dev['type'] ?? null) === 'camera') {
                    $entry = $this->stripForV1($dev);
                    break;
                }
            }
        }

        if ($exit === null && $entry !== null) {
            foreach ($devices as $dev) {
                if (($dev['type'] ?? null) === 'camera' && (int) ($dev['id'] ?? 0) !== (int) ($entry['id'] ?? 0)) {
                    $exit = $this->stripForV1($dev);
                    break;
                }
            }
        }

        return [
            'pair_id' => $base['pair_id'],
            'id' => $base['id'],
            'pair_name' => $base['pair_name'],
            'name' => $base['name'],
            'is_active' => $base['is_active'],
            'controller' => $controller,
            'entry_camera' => $entry,
            'exit_camera' => $exit,
        ];
    }

    private function toV2Payload(array $base, array $devices, array $v1Payload = []): array
    {
        return [
            'pair_id' => $base['pair_id'],
            'id' => $base['id'],
            'pair_name' => $base['pair_name'],
            'name' => $base['name'],
            'is_active' => $base['is_active'],
            'location_id' => $base['location_id'],
            'comment' => $base['comment'],
            'factory_code' => $base['factory_code'],
            'devices' => $devices,
            // сохраняем совместимость даже если клиент неожиданно ждёт v1-ключи
            'controller' => $v1Payload['controller'] ?? null,
            'entry_camera' => $v1Payload['entry_camera'] ?? null,
            'exit_camera' => $v1Payload['exit_camera'] ?? null,
        ];
    }

    private function resolveFactoryCode(PairDevice $pair): ?string
    {
        $locationName = trim((string) ($pair->locationRef->LocationName ?? ''));

        return $locationName === '' ? null : $locationName;
    }

    private function resolveRequestedFormat(Request $request): string
    {
        $raw = mb_strtolower(trim((string) ($request->query('format') ?? $request->input('format') ?? '')));
        $queryString = mb_strtolower((string) $request->server('QUERY_STRING', ''));

        if (
            in_array($raw, ['v2', '2', 'devices'], true)
            || str_contains($raw, 'v2')
            || str_contains($queryString, 'format=v2')
        ) {
            return 'v2';
        }

        return 'v1';
    }

    private function stripForV1(array $dev): array
    {
        return [
            'id' => (int) ($dev['id'] ?? 0),
            'type' => (string) ($dev['type'] ?? ''),
            'name' => $dev['name'] ?? null,
            'ip' => $dev['ip'] ?? null,
            'port' => $dev['port'] ?? null,
            'login' => $dev['login'] ?? null,
            'password' => $dev['password'] ?? null,
            'is_active' => (bool) ($dev['is_active'] ?? false),
        ];
    }

    /**
     * Преобразование ControllerDevice/Camera в общий формат.
     * Возвращает null, если устройства нет или оно не активно.
     *
     */
    private function mapDevice($dev, string $type, string $role = '', ?int $memberId = null, ?int $sortOrder = null): ?array
    {
        if (!$dev) {
            return null;
        }

        // Учитываем разные варианты наименования поля активности.
        $isActive = $dev->IsActive ?? $dev->active ?? $dev->is_active ?? 1;
        if (!(bool) $isActive) {
            return null;
        }

        // Забираем через аксессоры в первую очередь и поддерживаем legacy-варианты ключей.
        $ip = $dev->ip
            ?? $dev->ip_address
            ?? $dev->Ip_address
            ?? $dev->IP_address
            ?? $dev->IpAddress
            ?? $dev->IP
            ?? null;

        $port = $dev->port ?? $dev->Port ?? null;
        $login = $dev->login ?? $dev->Login ?? null;
        $location = $dev->location ?? $dev->Location ?? null;

        // ВАЖНО: сначала берём сырое значение Password (зашифрованная строка),
        // но поддерживаем и lowercase-варианты столбцов.
        $pwdEnc = null;
        try {
            $pwdEnc = $dev->getRawOriginal('Password')
                ?? $dev->getRawOriginal('password')
                ?? $dev->getOriginal('Password')
                ?? $dev->getOriginal('password');
        } catch (\Throwable $e) {
            // если нет такого поля в модели — пробуем просто свойство
            $pwdEnc = $dev->Password ?? $dev->password ?? null;
        }

        $pwd = null;
        if (is_string($pwdEnc) && $pwdEnc !== '') {
            try {
                $pwd = Crypt::decryptString($pwdEnc);
            } catch (DecryptException $e) {
                // Совместимость: в части установок пароль исторически хранится не шифрованным.
                // Если строка не похожа на laravel encrypted payload — отдаём как plain.
                if (!$this->looksLikeLaravelEncryptedPayload($pwdEnc)) {
                    $pwd = $pwdEnc;
                }

                Log::warning('DevicePairApiController: device password decrypt failed', [
                    'device_type' => $type,
                    'device_id' => (int) ($dev->id ?? $dev->Id ?? 0),
                    'password_fallback_plain' => $pwd !== null,
                ]);
            }
        }

        $ipNormalized = $ip !== null ? trim((string) $ip) : null;
        if ($ipNormalized === '') {
            $ipNormalized = null;
        }

        $loginNormalized = $login !== null ? trim((string) $login) : null;
        if ($loginNormalized === '') {
            $loginNormalized = null;
        }

        $passwordNormalized = $pwd !== null ? trim((string) $pwd) : null;
        if ($passwordNormalized === '') {
            $passwordNormalized = null;
        }

        return [
            'member_id' => $memberId,
            'id'        => (int) ($dev->id ?? 0),
            'type'      => $type,             // "controller" | "camera"
            'role'      => $role !== '' ? $role : $type,
            'name'      => $location !== null ? (string) $location : null,
            'ip'        => $ipNormalized,
            'port'      => $port !== null ? (int) $port : null,
            'login'     => $loginNormalized,
            'password'  => $passwordNormalized, // уже расшифрован или plain-fallback
            'is_active' => true,
            'sort_order' => $sortOrder ?? 100,
        ];
    }

    private function looksLikeLaravelEncryptedPayload(string $value): bool
    {
        $decoded = base64_decode($value, true);
        if ($decoded === false || $decoded === '') {
            return false;
        }

        $json = json_decode($decoded, true);
        if (!is_array($json)) {
            return false;
        }

        return isset($json['iv'], $json['value'], $json['mac']);
    }
}
