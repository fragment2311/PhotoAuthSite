<?php

namespace App\Http\Controllers;

use App\Models\ControllerDevice;
use App\Models\Camera;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Crypt;
use App\Models\PairDevice;

class DevicesController extends Controller
{
// страница формы (создание)
public function create()
{
    return view('admin.devices.create', [
        'device' => null,
        'type'   => null,
        'title'  => 'Добавить устройство',
    ]);
}



    // обработчик сохранения
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
                Rule::requiredIf(fn() => $request->input('device_type') === 'camera'),
                Rule::in(['entry','exit','other'])
            ],
        ]);

        $common = [
            'ip_address'       => $validated['ip_address'],
            'Port'             => $validated['Port'],
            'Login'            => $validated['Login'],
'Password'         => Crypt::encryptString($validated['Password']),
            'Location'         => $validated['Location'] ?? null,
            'IsActive'         => (int)($validated['IsActive'] ?? 1),
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
            ->with('status', 'Устройство добавлено');
    }

public function index()
{
    // контроллеры и камеры как раньше
    $controllers = ControllerDevice::orderByDesc('last_modified_at')->get();
    $cameras     = Camera::orderByDesc('last_modified_at')->get();

    // пары устройств из таблицы pairdevices
    $pairs = PairDevice::with(['controller', 'entryCamera', 'exitCamera'])
        ->orderByDesc('Updated_at')
        ->get();

    return view('admin.devices.index', compact('controllers', 'cameras', 'pairs'));
}


    /**
     * Вспомогательный метод: получить модель по типу.
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
            $rules['Direction'] = ['required', Rule::in(['entry', 'exit'])];
        }

        $data = $request->validate($rules);

        $device->ip_address = $data['ip_address'];
        $device->Port       = $data['Port'];
        $device->Login      = $data['Login'];
       
        if (!empty($data['Password'])) {
    $device->Password = Crypt::encryptString($data['Password']);
}

        $device->Location   = $data['Location'] ?? null;
        $device->IsActive   = !empty($data['IsActive']) ? 1 : 0;

        if ($type === 'camera') {
            $device->Direction = $data['Direction'];
        }

        $device->Last_modified_at = now();
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
        $device->IsActive = $current === 1 ? 0 : 1;
        $device->Last_modified_at = now();
        $device->save();

        return redirect()
            ->route('admin.devices.index')
            ->with('status', 'Статус устройства изменён.');
    }

// --- Пара устройств: страница создания ---
public function pairsCreate()
{
    // контроллеры и камеры берём из БД
    $controllers = ControllerDevice::orderBy('id')->get();
    $cameras     = Camera::orderBy('id')->get();

    // имя вьюхи подставь своё, если называл иначе
    return view('admin.devices.pairs_create', [
        'controllers' => $controllers,
        'cameras'     => $cameras,
    ]);
}


// --- Пара устройств: сохранение ---
public function pairsStore(Request $request)
{
    // Получаем реальные имена таблиц из моделей
    $controllersTable = (new ControllerDevice())->getTable();
    $camerasTable     = (new Camera())->getTable();

    $validated = $request->validate([
        'Name'            => 'required|string|max:100',
        'controller_id'   => "required|exists:{$controllersTable},id",
        'entry_camera_id' => "nullable|exists:{$camerasTable},id",
        'exit_camera_id'  => "nullable|exists:{$camerasTable},id|different:entry_camera_id",
        'Comment'         => 'nullable|string|max:255',
        'IsActive'        => 'required|boolean',
    ]);

    $pair = new PairDevice();
    $pair->Name            = $validated['Name'];
    $pair->Controller_id   = $validated['controller_id'];
    $pair->Entry_camera_id = $validated['entry_camera_id'];
    $pair->Exit_camera_id  = $validated['exit_camera_id'];
    $pair->Comment         = $validated['Comment'] ?? null;
    $pair->IsActive        = !empty($validated['IsActive']) ? 1 : 0;

    $pair->save();

    return redirect()
        ->back()
        ->with('status', 'Пара устройств успешно создана.');
}


public function pairsEdit($id)
{
    $pair = PairDevice::findOrFail($id);

    $controllers = ControllerDevice::orderBy('id')->get();
    $cameras     = Camera::orderBy('id')->get();

    return view('admin.devices.pairs_create', [
        'pair'        => $pair,
        'controllers' => $controllers,
        'cameras'     => $cameras,
    ]);
}

public function pairsUpdate(Request $request, $id)
{
    $pair = PairDevice::findOrFail($id);

    $controllersTable = (new ControllerDevice())->getTable();
    $camerasTable     = (new Camera())->getTable();

    $validated = $request->validate([
        'Name'            => 'required|string|max:100',
        'controller_id'   => "required|exists:{$controllersTable},id",
        'entry_camera_id' => "nullable|exists:{$camerasTable},id",
        'exit_camera_id'  => "nullable|exists:{$camerasTable},id|different:entry_camera_id",
        'Comment'         => 'nullable|string|max:255',
        'IsActive'        => 'nullable|boolean',
    ]);

    $pair->Name            = $validated['Name'];
    $pair->Controller_id   = $validated['controller_id'];
    $pair->Entry_camera_id = $validated['entry_camera_id'];
    $pair->Exit_camera_id  = $validated['exit_camera_id'];
    $pair->Comment         = $validated['Comment'] ?? null;
    $pair->IsActive        = !empty($validated['IsActive']) ? 1 : 0;

    $pair->save();

    return redirect()
        ->route('admin.devices.index')
        ->with('status', 'Пара успешно обновлена.');
}

public function pairsToggle($id)
{
    $pair = PairDevice::findOrFail($id);

    $pair->IsActive = $pair->IsActive ? 0 : 1;
    $pair->save();

    return redirect()
        ->route('admin.devices.index')
        ->with('status', 'Статус пары обновлён.');
}


}
