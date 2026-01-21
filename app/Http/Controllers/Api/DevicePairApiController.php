<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PairDevice;
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
    public function active(): JsonResponse
    {
        // ВАЖНО: не указываем "кривые" списки колонок в with(),
        // иначе Laravel может упасть из-за несуществующих полей.
        $pairs = PairDevice::with(['controller', 'entryCamera', 'exitCamera'])
            ->where('IsActive', 1)
            ->orderBy('id')
            ->get();

        $result = [];

        foreach ($pairs as $pair) {
            try {
                $result[] = [
                    'pair_id'      => (int) $pair->id,
                    'pair_name'    => (string) ($pair->Name ?? $pair->name ?? ''),
                    'is_active'    => (bool) ($pair->IsActive ?? 0),

                    'controller'   => $this->mapDevice($pair->controller, 'controller'),
                    'entry_camera' => $this->mapDevice($pair->entryCamera, 'camera'),
                    'exit_camera'  => $this->mapDevice($pair->exitCamera, 'camera'),
                ];
            } catch (DecryptException $e) {
                // Не раскрываем детали, но фиксируем в лог сервера
                Log::warning('DevicePairApiController: decrypt failed', [
                    'pair_id' => $pair->id,
                    'error'   => $e->getMessage(),
                ]);

                // Чтобы C# явно увидел проблему конфигурации
                $result[] = [
                    'pair_id'      => (int) $pair->id,
                    'pair_name'    => (string) ($pair->Name ?? $pair->name ?? ''),
                    'is_active'    => (bool) ($pair->IsActive ?? 0),
                    'controller'   => null,
                    'entry_camera' => null,
                    'exit_camera'  => null,
                    'warning'      => 'decryption_error',
                ];
            }
        }

        return response()->json($result);
    }

    /**
     * Преобразование ControllerDevice/Camera в общий формат.
     * Возвращает null, если устройства нет или оно не активно.
     *
     * @throws DecryptException
     */
    private function mapDevice($dev, string $type): ?array
    {
        if (!$dev) {
            return null;
        }

        // У тебя активность хранится как IsActive (с большой буквы)
        if (property_exists($dev, 'IsActive') || isset($dev->IsActive)) {
            if (!(bool) $dev->IsActive) {
                return null;
            }
        }

        // IP: у тебя в БД/моделях встречается ip_address (snake_case)
        // + иногда люди делают аксессор getIpAttribute(). Мы поддержим оба варианта.
        $ip = null;
        if (isset($dev->ip)) {
            $ip = (string) $dev->ip;
        } elseif (isset($dev->ip_address)) {
            $ip = (string) $dev->ip_address;
        } elseif (isset($dev->IpAddress)) {
            $ip = (string) $dev->IpAddress;
        } elseif (isset($dev->IP)) {
            $ip = (string) $dev->IP;
        }

        // Port/Login/Password/Location у тебя с большой буквы
        $port     = isset($dev->Port) ? (int) $dev->Port : (isset($dev->port) ? (int) $dev->port : null);
        $login    = isset($dev->Login) ? (string) $dev->Login : (isset($dev->login) ? (string) $dev->login : '');
        $location = isset($dev->Location) ? (string) $dev->Location : (isset($dev->location) ? (string) $dev->location : '');

        // ВАЖНО: берём сырое значение Password (зашифрованная строка), не мутатор/аксессор
        $pwdEnc = null;
        try {
            $pwdEnc = $dev->getRawOriginal('Password');
        } catch (\Throwable $e) {
            // если нет такого поля в модели — пробуем просто свойство
            $pwdEnc = $dev->Password ?? null;
        }

        $pwd = $pwdEnc ? Crypt::decryptString($pwdEnc) : null;

        return [
            'id'        => (int) ($dev->id ?? 0),
            'type'      => $type,             // "controller" | "camera"
            'name'      => $location,
            'ip'        => $ip ?: null,
            'port'      => $port,
            'login'     => $login ?: null,
            'password'  => $pwd,              // уже расшифрован
            'is_active' => true,
        ];
    }
}
