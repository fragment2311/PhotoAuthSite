<?php

namespace App\Http\Controllers;

use App\Models\ControllerDevice;
use App\Models\Camera;
use App\Models\Location;
use App\Models\PairDevice;
use App\Models\PairDeviceMember;
use App\Services\ChangeHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $pairs       = PairDevice::with(['members.controller', 'members.camera', 'locationRef'])
            ->orderBy('Id')
            ->get();

        $pairs->each(function (PairDevice $pair): void {
            $this->hydratePairEndpointRelations($pair);
        });

        return view('admin.devices.index', compact('controllers', 'cameras', 'pairs'));
    }

    /**
     * Форма создания устройства (камера/контроллер).
     */
    public function create()
    {
        $locations = $this->loadLocationsForSelect();

        return view('admin.devices.create', [
            'device'              => null,
            'type'                => null,
            'title'               => 'Создать устройство',
            'locations'           => $locations,
            'selectedLocationId'  => null,
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
            'Location'    => [
                Rule::requiredIf(fn () => $this->typeHasColumn((string) $request->input('device_type', ''), 'Location')),
                'nullable',
                'integer',
                'exists:location,IdLocation',
            ],
            'IsActive'    => ['nullable', 'boolean'],
            // только для камер:
            'Direction'   => [
                Rule::requiredIf(fn () => $request->input('device_type') === 'camera'),
                Rule::in(['entry', 'exit', 'other']),
            ],
        ], [
            'Location.required' => 'Поле «Расположение» обязательно для заполнения.',
            'Location.integer'  => 'Поле «Расположение» заполнено некорректно.',
            'Location.exists'   => 'Выбранная локация не найдена.',
        ], [
            'Location' => 'расположение',
        ]);

        $common = [
            'ip_address'       => $validated['ip_address'],
            'Port'             => $validated['Port'],
            'Login'            => $validated['Login'],
            // ВАЖНО: пароли сразу шифруем.
            'Password'         => Crypt::encryptString($validated['Password']),
            'IsActive'         => !empty($validated['IsActive']) ? 1 : 0,
            'Last_modified_at' => now(),
        ];

        if ($this->typeHasColumn($validated['device_type'], 'Location')) {
            $common['Location'] = $this->resolveDeviceLocationValue(
                isset($validated['Location']) ? (int) $validated['Location'] : null,
                $validated['device_type']
            );
        }

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
        $locations = $this->loadLocationsForSelect();
        $selectedLocationId = $this->resolveSelectedLocationId($device->location ?? null);
        $lastChange = app(ChangeHistoryService::class)
            ->latestFor(class_basename($device), $device->getKey());

        return view('admin.devices.create', [
            'device'              => $device,
            'type'                => $type,
            'locations'           => $locations,
            'selectedLocationId'  => $selectedLocationId,
            'lastChange'          => $lastChange,
            'title'               => $type === 'controller'
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
        $existingEncryptedPassword = $device->getOriginal('Password') ?? $device->getOriginal('password');

        $rules = [
            'ip_address' => ['required', 'ip'],
            'Port'       => ['required', 'integer', 'min:1', 'max:65535'],
            'Login'      => ['required', 'string', 'max:100'],
            'Password'   => [Rule::requiredIf(empty($existingEncryptedPassword)), 'nullable', 'string', 'max:255'],
            'Location'   => [
                Rule::requiredIf($this->modelHasColumn($device, 'Location')),
                'nullable',
                'integer',
                'exists:location,IdLocation',
            ],
            'IsActive'   => ['nullable', 'boolean'],
        ];

        if ($type === 'camera') {
            $rules['Direction'] = ['required', Rule::in(['entry', 'exit', 'other'])];
        }

        $data = $request->validate($rules, [
            'Password.required' => 'Пароль обязателен, так как у устройства ещё не задан пароль.',
            'Location.required' => 'Поле «Расположение» обязательно для заполнения.',
            'Location.integer'  => 'Поле «Расположение» заполнено некорректно.',
            'Location.exists'   => 'Выбранная локация не найдена.',
        ], [
            'Password' => 'пароль',
            'Location' => 'расположение',
        ]);

        $device->ip_address       = $data['ip_address'];
        $device->Port             = $data['Port'];
        $device->Login            = $data['Login'];

        // Пароль при редактировании НЕ обязателен
        if (!empty($data['Password'])) {
            $device->Password = Crypt::encryptString($data['Password']);
        }

        if ($this->modelHasColumn($device, 'Location')) {
            $device->Location = $this->resolveDeviceLocationValue(
                isset($data['Location']) ? (int) $data['Location'] : null,
                $type
            );
        }
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
            'pairRelations' => [],
        ]);
    }

    /**
     * Сохранение новой пары устройств.
     */
    public function pairsStore(Request $request)
    {
        $this->ensureAdminAccess();

        $validated = $this->validatePairRequest($request);

        DB::transaction(function () use ($validated): void {

            $pair = new PairDevice();
            $pair->Name             = $validated['Name'];
            $pair->Location         = (int) $validated['location_id'];
            $pair->Comment          = $validated['Comment'] ?? null;
            $pair->IsActive         = !empty($validated['IsActive']) ? 1 : 0;
            $pair->Last_modified_at = now();

            $pair->save();

            $this->syncPairMembers($pair, $validated);
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
        $lastChange = app(ChangeHistoryService::class)
            ->latestFor(class_basename($pair), $pair->getKey());

        $controllers = ControllerDevice::where('IsActive', 1)->orderBy('id')->get();
        $cameras     = Camera::where('IsActive', 1)->orderBy('id')->get();
        $locations   = DB::table('location')
            ->select('IdLocation', 'LocationName')
            ->orderBy('LocationName')
            ->get();

        $relations = $this->loadPairRelationsForForm($pair);

        return view('admin.devices.pairs_create', [
            'pair'        => $pair,
            'controllers' => $controllers,
            'cameras'     => $cameras,
            'locations'   => $locations,
            'pairRelations' => $relations,
            'lastChange'  => $lastChange,
        ]);
    }

    /**
     * Обновление пары устройств.
     */
    public function pairsUpdate(Request $request, int $id)
    {
        $this->ensureAdminAccess();

        $pair = PairDevice::findOrFail($id);

        $validated = $this->validatePairRequest($request, $pair);

        DB::transaction(function () use ($validated, $pair): void {

            $pair->Name             = $validated['Name'];
            $pair->Location         = (int) $validated['location_id'];
            $pair->Comment          = $validated['Comment'] ?? null;
            $pair->IsActive         = !empty($validated['IsActive']) ? 1 : 0;
            $pair->Last_modified_at = now();

            $pair->save();

            $this->syncPairMembers($pair, $validated);
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

    private function typeHasColumn(string $type, string $column): bool
    {
        $model = $type === 'camera' ? new Camera() : new ControllerDevice();

        return $this->modelHasColumn($model, $column);
    }

    private function loadLocationsForSelect()
    {
        return Location::query()
            ->select('IdLocation', 'LocationName')
            ->orderBy('LocationName')
            ->get();
    }

    private function resolveDeviceLocationValue(?int $locationId, string $deviceType): mixed
    {
        if (empty($locationId)) {
            return null;
        }

        $location = Location::query()->find($locationId);
        if (!$location) {
            return null;
        }

        $model = $deviceType === 'camera' ? new Camera() : new ControllerDevice();

        try {
            $columnType = Schema::connection($model->getConnectionName())
                ->getColumnType($model->getTable(), 'Location');

            if (str_contains(mb_strtolower((string) $columnType), 'int')) {
                return (int) $location->IdLocation;
            }
        } catch (\Throwable $e) {
            // Если тип колонки определить не удалось, используем безопасный fallback ниже.
        }

        return (string) $location->LocationName;
    }

    private function resolveSelectedLocationId($rawLocation): ?int
    {
        if ($rawLocation === null || $rawLocation === '') {
            return null;
        }

        if (is_numeric($rawLocation)) {
            $id = (int) $rawLocation;
            $exists = Location::query()->where('IdLocation', $id)->exists();

            return $exists ? $id : null;
        }

        $normalized = mb_strtolower(trim((string) $rawLocation));
        if ($normalized === '') {
            return null;
        }

        $found = Location::query()
            ->whereRaw('LOWER(TRIM(LocationName)) = ?', [$normalized])
            ->first();

        return $found ? (int) $found->IdLocation : null;
    }

    private function modelHasColumn($model, string $column): bool
    {
        try {
            return Schema::connection($model->getConnectionName())
                ->hasColumn($model->getTable(), $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function ensureAdminAccess(): void
    {
        $user = auth()->user();
        $role = mb_strtolower(trim((string) data_get($user, 'role.NameRole', '')));

        abort_unless($user && $role === 'администратор', 403, 'Доступ запрещён.');
    }

    private function validatePairRequest(Request $request, ?PairDevice $pair = null): array
    {
        $controllersTable = (new ControllerDevice())->getTable();
        $camerasTable = (new Camera())->getTable();

        $validated = $request->validate([
            'Name' => ['required', 'string', 'max:100'],
            'location_id' => ['required', 'integer', 'exists:location,IdLocation'],
            'Comment' => ['nullable', 'string', 'max:255'],
            'IsActive' => ['nullable', 'boolean'],
            'controller_id' => ['required', 'integer', "exists:{$controllersTable},id"],
            'entry_camera_id' => ['nullable', 'integer', "exists:{$camerasTable},id", 'different:exit_camera_id'],
            'exit_camera_id' => ['nullable', 'integer', "exists:{$camerasTable},id"],
        ], [
            'location_id.required' => 'Выберите точку.',
            'controller_id.required' => 'Выберите контроллер.',
            'entry_camera_id.different' => 'Одна камера не может быть назначена одновременно для входа и выхода.',
        ]);

        $this->ensureDevicesAreFree($validated, $pair?->Id);

        return $validated;
    }

    private function ensureDevicesAreFree(array $validated, ?int $currentPairId): void
    {
        $checks = [
            ['IdController', (int) ($validated['controller_id'] ?? 0), 'Контроллер уже назначен другой точке.'],
            ['IdCamera', (int) ($validated['entry_camera_id'] ?? 0), 'Камера уже назначена другой точке.'],
            ['IdCamera', (int) ($validated['exit_camera_id'] ?? 0), 'Камера уже назначена другой точке.'],
        ];

        foreach ($checks as [$column, $deviceId, $message]) {
            if ($deviceId < 1) {
                continue;
            }

            $query = PairDeviceMember::query()->where($column, $deviceId);
            if ($currentPairId !== null) {
                $query->where('IdPairDevice', '!=', $currentPairId);
            }

            if ($query->exists()) {
                throw ValidationException::withMessages([$column => $message]);
            }
        }
    }

    private function syncPairMembers(PairDevice $pair, array $validated): void
    {
        PairDeviceMember::query()->where('IdPairDevice', $pair->Id)->delete();

        $members = [[
            'IdPairDevice' => $pair->Id,
            'IdController' => (int) $validated['controller_id'],
            'IdCamera' => null,
            'Role' => 'controller',
            'SortOrder' => 10,
            'IsActive' => 1,
        ]];

        foreach (['entry_camera_id' => ['entry_camera', 20], 'exit_camera_id' => ['exit_camera', 30]] as $field => [$role, $sortOrder]) {
            $cameraId = (int) ($validated[$field] ?? 0);
            if ($cameraId > 0) {
                $members[] = [
                    'IdPairDevice' => $pair->Id,
                    'IdController' => null,
                    'IdCamera' => $cameraId,
                    'Role' => $role,
                    'SortOrder' => $sortOrder,
                    'IsActive' => 1,
                ];
            }
        }

        PairDeviceMember::query()->insert($members);
    }

    private function loadPairRelationsForForm(PairDevice $pair): array
    {
        $members = $pair->relationLoaded('members') ? $pair->members : $pair->members()->get();

        return [
            'controller_id' => (int) optional($members->firstWhere('Role', 'controller'))->IdController,
            'entry_camera_id' => (int) optional($members->firstWhere('Role', 'entry_camera'))->IdCamera,
            'exit_camera_id' => (int) optional($members->firstWhere('Role', 'exit_camera'))->IdCamera,
        ];
    }

    private function hydratePairEndpointRelations(PairDevice $pair): void
    {
        $members = $pair->members;
        $pair->setRelation('controller', optional($members->firstWhere('Role', 'controller'))->controller);
        $pair->setRelation('entryCamera', optional($members->firstWhere('Role', 'entry_camera'))->camera);
        $pair->setRelation('exitCamera', optional($members->firstWhere('Role', 'exit_camera'))->camera);
    }
}
