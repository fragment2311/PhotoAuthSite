<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\FtpAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DevicesController;
use App\Http\Controllers\Api\DevicePairApiController;
use App\Http\Controllers\PassageController;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Admin\SettingsController;
use App\Services\BootstrapConfigService;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 1) Корень
Route::get('/', function (Request $request) {
    $bootstrap = app(BootstrapConfigService::class);

    if (!$bootstrap->isConfigured()) {
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $request->session()->forget('setup_access_granted');
        return redirect()->route('setup.auth', $request->only('setup_key'));
    }

    $rootDir = trim((string) config('app.gallery_root_dir', 'entrance_photos'), '/');
    if ($rootDir === '') {
        $rootDir = 'entrance_photos';
    }

    return Auth::check()
        ? redirect()->route('gallery', ['dir' => $rootDir])
        : redirect()->route('login');
})->name('root');

// 2) Гость: вход
Route::get('/setup/auth', [SettingsController::class, 'setupAuth'])->name('setup.auth');
Route::post('/setup/auth', [SettingsController::class, 'setupAuthSubmit'])->name('setup.auth.submit');

Route::get('/setup/settings', [SettingsController::class, 'setupIndex'])->name('setup.settings');
Route::post('/setup/settings', [SettingsController::class, 'setupSave'])->name('setup.settings.save');
Route::get('/setup/settings/device-pairs-api-token', [SettingsController::class, 'setupDevicePairsApiToken'])
    ->name('setup.settings.devicePairsApiToken');
Route::post('/setup/settings/device-pairs-api-token/generate', [SettingsController::class, 'setupGenerateDevicePairsApiToken'])
    ->name('setup.settings.devicePairsApiToken.generate');

Route::middleware('guest')->group(function () {
    Route::get('/login', [FtpAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [FtpAuthController::class, 'login']);
});

// 3) Авторизованные
Route::middleware('auth')->group(function () {

    // Выход
    Route::post('/logout', [FtpAuthController::class, 'logout'])->name('logout');

    // Галерея
    Route::get('/gallery/{dir?}', [FtpAuthController::class, 'gallery'])
        ->where('dir', '.*')
        ->name('gallery');

        Route::get('/all-passages', [FtpAuthController::class, 'allPassages'])
    ->name('all.passages');



    Route::get('/passage', [PassageController::class, 'index'])->name('passage.index');
    Route::get('/work-hours', [\App\Http\Controllers\WorkHoursController::class, 'index'])
    ->name('workhours.index');
    Route::get('/admin/passages/export', [\App\Http\Controllers\PassageController::class, 'export'])
    ->name('admin.passages.export');



    // Админ (просмотр/редактирование) — любой залогиненный
    Route::get('/admin/users', [AdminController::class, 'users'])->name('admin.users');
    Route::get('/admin/users/{user}/edit', [AdminController::class, 'edit'])->name('admin.users.edit');

    // Админ только для роли "Администратор"
    Route::middleware('role:Администратор')
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {

            // Создание пользователя
            Route::get('register', [AdminController::class, 'showRegister'])->name('register');
            Route::post('register', [AdminController::class, 'register'])->name('register.post');

            // Сохранение изменений пользователя
            Route::patch('users/{user}', [AdminController::class, 'update'])->name('users.update');

            // Блокировка / активация пользователя
            Route::patch('users/{user}/toggle', [AdminController::class, 'toggleActive'])
                ->name('users.toggle');

            // =========================
            // УСТРОЙСТВА (контроллеры и камеры)
            // =========================

            // список устройств
            Route::get('devices', [DevicesController::class, 'index'])->name('devices.index');

            // страница добавления
            Route::get('devices/create', [DevicesController::class, 'create'])->name('devices.create');

            // сохранение нового устройства
            Route::post('devices', [DevicesController::class, 'store'])->name('devices.store');

            // страница редактирования устройства (контроллер / камера)
            Route::get('devices/{type}/{id}/edit', [DevicesController::class, 'edit'])
                ->where('type', 'controller|camera')
                ->whereNumber('id')
                ->name('devices.edit');

            // сохранение изменений устройства
            Route::put('devices/{type}/{id}', [DevicesController::class, 'update'])
                ->where('type', 'controller|camera')
                ->whereNumber('id')
                ->name('devices.update');

            // блокировка / разблокировка устройства
            Route::patch('devices/{type}/{id}/toggle', [DevicesController::class, 'toggle'])
                ->where('type', 'controller|camera')
                ->whereNumber('id')
                ->name('devices.toggle');

                Route::patch('/pairs/{id}/toggle', [DevicesController::class, 'pairsToggle'])
                    ->name('pairs.toggle');

            // =========================
            // ПАРЫ УСТРОЙСТВ
            // =========================

            // страница создания пары
            Route::get('device_pairs/create', [DevicesController::class, 'pairsCreate'])
                ->name('device_pairs.create');

            // сохранение новой пары
            Route::post('device_pairs', [DevicesController::class, 'pairsStore'])
                ->name('device_pairs.store');

            // страница редактирования пары
            Route::get('device_pairs/{id}/edit', [DevicesController::class, 'pairsEdit'])
                ->whereNumber('id')
                ->name('device_pairs.edit');

            // сохранение изменений пары
            Route::put('device_pairs/{id}', [DevicesController::class, 'pairsUpdate'])
                ->whereNumber('id')
                ->name('device_pairs.update');

            // блокировка / разблокировка пары
            Route::patch('device_pairs/{id}/toggle', [DevicesController::class, 'pairsToggle'])
                ->whereNumber('id')
                ->name('device_pairs.toggle');

            
            //Настройки
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingsController::class, 'save'])->name('settings.save');

         Route::post('/settings/test-db', [SettingsController::class, 'testDb'])->name('settings.testDb');
         Route::post('/settings/test-storage', [SettingsController::class, 'testStorage'])->name('settings.testStorage');
         Route::post('/settings/license', [SettingsController::class, 'uploadLicense'])
    ->name('settings.license.upload');
         Route::get('/settings/device-pairs-api-token', [SettingsController::class, 'devicePairsApiToken'])
    ->name('settings.devicePairsApiToken');
         Route::post('/settings/device-pairs-api-token/generate', [SettingsController::class, 'generateDevicePairsApiToken'])
    ->name('settings.devicePairsApiToken.generate');

        });

   
Route::middleware(['api', 'api.token'])->get('device-pairs/active', [DevicePairApiController::class, 'active']);

});
