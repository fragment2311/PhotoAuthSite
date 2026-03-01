<?php

namespace App\Http\Controllers;

use App\Models\ControllerDevice;
use App\Models\Camera;
use App\Models\Location;
use App\Models\PairDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DevicesController extends Controller
{
    /**
     * Список устройств и пар (админка).
     */
    public function index()
    {
        $controllers = ControllerDevice::orderBy('id')->get();
        $cameras     = Camera::orderBy('id')->get();
        $pairs       = PairDevice::with(['controller', 'entryCamera', 'exitCamera'])
            ->orderBy('Id')
            ->get();

        return view('admin.devices.index', compact('controllers', 'cameras', 'pairs'));
    }

    /**
     * Форма создания устройства (камера/контроллер).
     */
    public function create()
    {
        return view('admin.devices.create', [
            'device' => null,
            'type'   => null,
            'title'  => 'Создать устройство',
        ]);
    }

    /**
     * Сохранение нового устройства.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'device_type' => ['required', Rule::in(['controller', 'camera'])],
            'ip_address'  => ['required', 'ip'],
            'Port'        => ['required', 'integer', 'min:1', 'max:65535'],
            'Login'       => ['required', 'string', 'max:100'],
            'Password'    => ['required', 'string', 'max:255'],
            'Location'    => ['nullable', 'string', 'max:255'],
            'IsActive'    => ['nullable', 'boolean'],
            // только для камер:
            'Direction'   => [
                Rule::requiredIf(fn () => $request->input('device_type') === 'camera'),
                Rule::in(['entry', 'exit', 'other']),
            ],
        ]);

        $common = [
            'ip_address'       => $validated['ip_address'],
            'Port'             => $validated['Port'],
            'Login'            => $validated['Login'],
            // ВАЖНО: пароли сразу шифруем.
            'Password'         => Crypt::encryptString($validated['Password']),
            'Location'         => $validated['Location'] ?? null,
            'IsActive'         => !empty($validated['IsActive']) ? 1 : 0,
            'Last_modified_at' => now(),
        ];

        if ($validated['device_type'] === 'camera') {
            $payload = $common + ['Direction' => $validated['Direction']];
            Camera::create($payload);
        } else {
            ControllerDevice::create($common);
        }

        return redirect()
            ->route('admin.devices.index')
            ->with('status', 'Устройство успешно создано.');
    }

    /**
     * Определение устройства по типу.
     */
    private function resolveDevice(string $type, int $id)
    {
        if ($type === 'controller') {
            return ControllerDevice::findOrFail($id);
        }

        if ($type === 'camera') {
            return Camera::findOrFail($id);
        }

        abort(404);
    }

    /**
     * Форма редактирования устройства.
     */
    public function edit(string $type, int $id)
    {
        $device = $this->resolveDevice($type, $id);

        return view('admin.devices.create', [
            'device' => $device,
            'type'   => $type,
            'title'  => $type === 'controller'
                ? 'Редактировать контроллер'
                : 'Редактировать камеру',
        ]);
    }

    /**
     * Сохранение изменений устройства.
     */
    public function update(Request $request, string $type, int $id)
    {
        $device = $this->resolveDevice($type, $id);

        $rules = [
            'ip_address' => ['required', 'ip'],
            'Port'       => ['required', 'integer', 'min:1', 'max:65535'],
            'Login'      => ['required', 'string', 'max:100'],
            'Password'   => ['nullable', 'string', 'max:255'],
            'Location'   => ['nullable', 'string', 'max:255'],
            'IsActive'   => ['nullable', 'boolean'],
        ];

        if ($type === 'camera') {
            $rules['Direction'] = ['required', Rule::in(['entry', 'exit', 'other'])];
        }

        $data = $request->validate($rules);

        $device->ip_address       = $data['ip_address'];
        $device->Port             = $data['Port'];
        $device->Login            = $data['Login'];

        // Пароль при редактировании НЕ обязателен
        if (!empty($data['Password'])) {
            $device->Password = Crypt::encryptString($data['Password']);
        }

        $device->Location         = $data['Location'] ?? null;
        $device->IsActive         = !empty($data['IsActive']) ? 1 : 0;
        $device->Last_modified_at = now();

        if ($type === 'camera') {
            $device->Direction = $data['Direction'];
        }

        $device->save();

        return redirect()
            ->route('admin.devices.index')
            ->with('status', 'Устройство обновлено.');
    }

    /**
     * Блокировка / разблокировка устройства.
     */
    public function toggle(string $type, int $id)
    {
        $device = $this->resolveDevice($type, $id);

        $current = (int)($device->active ?? 0);
        $device->IsActive = $current ? 0 : 1;
        $device->Last_modified_at = now();
        $device->save();

        return redirect()
            ->route('admin.devices.index')
            ->with('status', 'Статус устройства обновлён.');
    }

    // =========================
    // Пары устройств (pairdevices)
    // =========================

    /**
     * Форма создания пары.
     */
    public function pairsCreate()
    {
        $controllers = ControllerDevice::where('IsActive', 1)->orderBy('id')->get();
        $cameras     = Camera::where('IsActive', 1)->orderBy('id')->get();
        $locations   = DB::table('location')
            ->select('IdLocation', 'LocationName')
            ->orderBy('LocationName')
            ->get();

        return view('admin.devices.pairs_create', [
            'pair'        => null,
            'controllers' => $controllers,
            'cameras'     => $cameras,
            'locations'   => $locations,
        ]);
    }

    /**
     * Сохранение новой пары устройств.
     */
    public function pairsStore(Request $request)
    {
        $this->ensureAdminAccess();

        $controllersTable = (new ControllerDevice())->getTable();
        $camerasTable     = (new Camera())->getTable();

        $validated = $request->validate([
            'Name'            => 'required|string|max:100',
            'location_id'     => ['nullable', 'integer', 'exists:location,IdLocation', 'required_without:new_location'],
            'new_location'    => ['nullable', 'string', 'max:150', 'required_without:location_id', 'regex:/^[\pL\pN\s\-.,()#\/]+$/u'],
            'controller_id'   => "required|integer|exists:{$controllersTable},id",
            'entry_camera_id' => "required|integer|exists:{$camerasTable},id",
            'exit_camera_id'  => "nullable|integer|different:entry_camera_id|exists:{$camerasTable},id",
            'Comment'         => 'nullable|string|max:255',
            'IsActive'        => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($validated): void {
            $resolvedLocationId = $this->resolveLocationId(
                isset($validated['location_id']) ? (int) $validated['location_id'] : null,
                $validated['new_location'] ?? null
            );

            $pair = new PairDevice();
            $pair->Name             = $validated['Name'];
            $pair->Location         = $resolvedLocationId;
            $pair->Controller_id    = $validated['controller_id'];
            $pair->Entry_camera_id  = $validated['entry_camera_id'];
            $pair->Exit_camera_id   = $validated['exit_camera_id'] ?? null;
            $pair->Comment          = $validated['Comment'] ?? null;
            $pair->IsActive         = !empty($validated['IsActive']) ? 1 : 0;
            $pair->Last_modified_at = now();

            $pair->save();
        });

        return redirect()
            ->route('admin.devices.index')
            ->with('status', 'Пара устройств успешно создана.');
    }

    /**
     * Форма редактирования пары.
     */
    public function pairsEdit(int $id)
    {
        $pair = PairDevice::findOrFail($id);

        $controllers = ControllerDevice::where('IsActive', 1)->orderBy('id')->get();
        $cameras     = Camera::where('IsActive', 1)->orderBy('id')->get();
        $locations   = DB::table('location')
            ->select('IdLocation', 'LocationName')
            ->orderBy('LocationName')
            ->get();

        return view('admin.devices.pairs_create', [
            'pair'        => $pair,
            'controllers' => $controllers,
            'cameras'     => $cameras,
            'locations'   => $locations,
        ]);
    }

    /**
     * Обновление пары устройств.
     */
    public function pairsUpdate(Request $request, int $id)
    {
        $this->ensureAdminAccess();

        $pair = PairDevice::findOrFail($id);

        $controllersTable = (new ControllerDevice())->getTable();
        $camerasTable     = (new Camera())->getTable();

        $validated = $request->validate([
            'Name'            => 'required|string|max:100',
            'location_id'     => ['nullable', 'integer', 'exists:location,IdLocation', 'required_without:new_location'],
            'new_location'    => ['nullable', 'string', 'max:150', 'required_without:location_id', 'regex:/^[\pL\pN\s\-.,()#\/]+$/u'],
            'controller_id'   => "required|integer|exists:{$controllersTable},id",
            'entry_camera_id' => "required|integer|exists:{$camerasTable},id",
            'exit_camera_id'  => "nullable|integer|different:entry_camera_id|exists:{$camerasTable},id",
            'Comment'         => 'nullable|string|max:255',
            'IsActive'        => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($validated, $pair): void {
            $resolvedLocationId = $this->resolveLocationId(
                isset($validated['location_id']) ? (int) $validated['location_id'] : null,
                $validated['new_location'] ?? null
            );

            $pair->Name             = $validated['Name'];
            $pair->Location         = $resolvedLocationId;
            $pair->Controller_id    = $validated['controller_id'];
            $pair->Entry_camera_id  = $validated['entry_camera_id'];
            $pair->Exit_camera_id   = $validated['exit_camera_id'] ?? null;
            $pair->Comment          = $validated['Comment'] ?? null;
            $pair->IsActive         = !empty($validated['IsActive']) ? 1 : 0;
            $pair->Last_modified_at = now();

            $pair->save();
        });

        return redirect()
            ->route('admin.devices.index')
            ->with('status', 'Пара успешно обновлена.');
    }

    /**
     * Переключение активности пары.
     */
    public function pairsToggle(int $id)
    {
        $pair = PairDevice::findOrFail($id);

        $pair->IsActive = $pair->IsActive ? 0 : 1;
        $pair->Last_modified_at = now();
        $pair->save();

        return redirect()
            ->route('admin.devices.index')
            ->with('status', 'Статус пары обновлён.');
    }

    private function resolveLocationId(?int $locationId, ?string $newLocation): int
    {
        $name = preg_replace('/\s+/u', ' ', trim((string) $newLocation));
        if ($name !== '') {
            $normalized = mb_strtolower($name);

            $existing = Location::query()
                ->whereRaw('LOWER(TRIM(LocationName)) = ?', [$normalized])
                ->first();

            if ($existing) {
                return (int) $existing->IdLocation;
            }

            $created = Location::create([
                'LocationName' => $name,
            ]);

            return (int) $created->IdLocation;
        }

        if (!empty($locationId)) {
            return (int) $locationId;
        }

        throw ValidationException::withMessages([
            'location_id' => 'Нужно выбрать существующую локацию или ввести новую.',
        ]);
    }

    private function ensureAdminAccess(): void
    {
        $user = auth()->user();
        $role = mb_strtolower(trim((string) data_get($user, 'role.NameRole', '')));

        abort_unless($user && $role === 'администратор', 403, 'Доступ запрещён.');
    }

    // =========================
    // INTERNAL API для SmartPass
    // =========================

    /**
     * GET /api/device-pairs/active
     *
     * Возвращает список всех АКТИВНЫХ пар устройств с расшифрованными паролями
     * контроллера и камер.
     *
     * Безопасность:
     *  - endpoint защищён middleware 'smartpass.api', который проверяет X-Api-Token;
     *  - используется только для доверенного C#-сервиса SmartPass;
     *  - пароли расшифровываются ТОЛЬКО здесь и не логируются.
     */
    public function apiActivePairs()
    {
        $pairs = PairDevice::with(['controller', 'entryCamera', 'exitCamera'])
            ->where('IsActive', 1)
            ->get();

        $result = [];

        foreach ($pairs as $pair) {
            // Пара без контроллера – пропускаем (неконсистентные данные)
            if (!$pair->controller) {
                continue;
            }

            $controller = $pair->controller;
            $entry      = $pair->entryCamera;
            $exit       = $pair->exitCamera;

            $result[] = [
                'pair_id'      => (int)$pair->id,
                'pair_name'    => $pair->name,
                'comment'      => $pair->Comment ?? null,
                'is_active'    => (bool)$pair->active,
                'controller'   => $this->serializeDeviceForApi($controller),
                'entry_camera' => $entry ? $this->serializeDeviceForApi($entry) : null,
                'exit_camera'  => $exit ? $this->serializeDeviceForApi($exit) : null,
            ];
        }

        return response()->json($result);
    }

    /**
     * Преобразование камеры/контроллера в JSON-структуру для API,
     * с расшифровкой пароля.
     *
     * Здесь сознательно:
     *  - не логируем пароль и зашифрованную строку;
     *  - используем getOriginal('Password'), чтобы явно взять значение из БД;
     *  - в случае ошибки расшифровки возвращаем пароль = null, чтобы C#-клиент
     *    мог отреагировать (например, залогировать на своей стороне).
     */
    private function serializeDeviceForApi($device): array
    {
        // Нормализованные геттеры (ip, port, login, location, active, id)
        $id        = (int)$device->id;
        $ip        = $device->ip;
        $port      = $device->port !== null ? (int)$device->port : null;
        $login     = $device->login;
        $location  = $device->location;
        $isActive  = (bool)$device->active;

        // Берём зашифрованное значение напрямую из атрибутов
        $encrypted = $device->getOriginal('Password') ?? $device->getOriginal('password');

        $password = null;
        if (!empty($encrypted)) {
            try {
                $password = Crypt::decryptString($encrypted);
            } catch (\Throwable $e) {
                // Никаких логов с паролем или ciphertext здесь,
                // максимум – общий лог без данных, если понадобится.
                $password = null;
            }
        }

        return [
            'id'        => $id,
            // "name" – у тебя отдельного поля нет, поэтому
            // логично отдать Location как некое человекочитаемое имя.
            'name'      => $location,
            'ip'        => $ip,
            'port'      => $port,
            'login'     => $login,
            'password'  => $password,
            'location'  => $location,
            'is_active' => $isActive,
        ];
    }

    /**
     * Получение активных пар устройств.
     */
    public function getActiveDevicePairs(Request $request)
    {
        // Проверка API токена (без анонимного доступа)
        $this->authorize('access-api', Role::class);

        // Получаем активные пары устройств с контроллером и камерами
        $pairs = PairDevice::with(['controller', 'entryCamera', 'exitCamera'])
            ->where('IsActive', 1)
            ->get();

        // Подготовка данных для ответа
        $response = $pairs->map(function ($pair) {
            return [
                'pair_id' => $pair->Id,
                'pair_name' => $pair->Name,
                'controller' => [
                    'id' => $pair->controller->Id,
                    'ip' => $pair->controller->Ip,
                    'port' => $pair->controller->Port,
                    'login' => $pair->controller->Login,
                    'password' => Crypt::decryptString($pair->controller->Password),  // Расшифровка пароля контроллера
                ],
                'entry_camera' => $pair->entryCamera ? [
                    'id' => $pair->entryCamera->Id,
                    'ip' => $pair->entryCamera->Ip,
                    'port' => $pair->entryCamera->Port,
                    'login' => $pair->entryCamera->Login,
                    'password' => Crypt::decryptString($pair->entryCamera->Password),  // Расшифровка пароля камеры входа
                ] : null,
                'exit_camera' => $pair->exitCamera ? [
                    'id' => $pair->exitCamera->Id,
                    'ip' => $pair->exitCamera->Ip,
                    'port' => $pair->exitCamera->Port,
                    'login' => $pair->exitCamera->Login,
                    'password' => Crypt::decryptString($pair->exitCamera->Password),  // Расшифровка пароля камеры выхода
                ] : null,
                'is_active' => $pair->IsActive,
            ];
        });

        return response()->json($response);
    }
}
