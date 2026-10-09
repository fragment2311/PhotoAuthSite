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
use App\Http\Controllers\Admin\LocationsController;
use App\Http\Controllers\Admin\ErrorLogController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UpdatesController;
use App\Services\BootstrapConfigService;
use App\Support\MasterAuthUser;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 1) РљРѕСЂРµРЅСЊ
Route::get('/', function (Request $request) {
    $bootstrap = app(BootstrapConfigService::class);
    $hasMasterGuardSession = false;
    try {
        $guard = Auth::guard();
        if (method_exists($guard, 'getName')) {
            $guardSessionKey = (string) $guard->getName();
            if ($guardSessionKey !== '') {
                $hasMasterGuardSession = (string) $request->session()->get($guardSessionKey, '') === 'master-auth-user';
            }
        }
    } catch (\Throwable $e) {
        $hasMasterGuardSession = false;
    }

    $masterSession = (bool) $request->session()->get('master_auth', false) || $hasMasterGuardSession;

    if ($masterSession) {
        Auth::setUser(new MasterAuthUser($bootstrap->getMasterLogin()));
        $request->session()->put('master_auth', true);
    }

    if (!$bootstrap->isConfigured()) {
        if ($masterSession || Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $request->session()->forget('setup_access_granted');
        return redirect()->route('setup.auth', $request->only('setup_key'));
    }

    $rootDir = trim((string) config('app.gallery_root_dir', config('app.gallery_root_dir_default', '')), '/');

    return ($masterSession || Auth::check())
        ? redirect()->route('gallery', ['dir' => $rootDir])
        : redirect()->route('login');
})->name('root');

// 2) Р“РѕСЃС‚СЊ: РІС…РѕРґ
Route::get('/setup/auth', [SettingsController::class, 'setupAuth'])->name('setup.auth');
Route::post('/setup/auth', [SettingsController::class, 'setupAuthSubmit'])->name('setup.auth.submit');

Route::get('/setup/settings', [SettingsController::class, 'setupIndex'])->name('setup.settings');
Route::post('/setup/settings', [SettingsController::class, 'setupSave'])->name('setup.settings.save');
Route::get('/setup/settings/device-pairs-api-token', [SettingsController::class, 'setupDevicePairsApiToken'])
    ->name('setup.settings.devicePairsApiToken');
Route::post('/setup/settings/device-pairs-api-token/generate', [SettingsController::class, 'setupGenerateDevicePairsApiToken'])
    ->name('setup.settings.devicePairsApiToken.generate');

Route::middleware(\App\Http\Middleware\RedirectIfAuthenticated::class)->group(function () {
    Route::get('/login', [FtpAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [FtpAuthController::class, 'login']);
});

// 3) РђРІС‚РѕСЂРёР·РѕРІР°РЅРЅС‹Рµ
Route::middleware(\App\Http\Middleware\Authenticate::class)->group(function () {

    // Р’С‹С…РѕРґ
    Route::post('/logout', [FtpAuthController::class, 'logout'])->name('logout');

    // Р“Р°Р»РµСЂРµСЏ
    Route::get('/gallery/{dir?}', [FtpAuthController::class, 'gallery'])
        ->where('dir', '.*')
        ->name('gallery');

        Route::get('/all-passages', [FtpAuthController::class, 'allPassages'])
    ->name('all.passages');
    Route::get('/media/thumbnail/{width}', [\App\Http\Controllers\ThumbnailController::class, 'show'])
        ->whereNumber('width')
        ->name('media.thumbnail');



    Route::get('/passage', [PassageController::class, 'index'])->name('passage.index');
    Route::get('/work-hours', [\App\Http\Controllers\WorkHoursController::class, 'index'])
    ->name('workhours.index');
    Route::get('/admin/passages/export', [\App\Http\Controllers\PassageController::class, 'export'])
    ->name('admin.passages.export');



    // РђРґРјРёРЅ (РїСЂРѕСЃРјРѕС‚СЂ/СЂРµРґР°РєС‚РёСЂРѕРІР°РЅРёРµ) вЂ” Р»СЋР±РѕР№ Р·Р°Р»РѕРіРёРЅРµРЅРЅС‹Р№
    Route::get('/admin/users', [AdminController::class, 'users'])->name('admin.users');
    Route::get('/admin/users/{user}/edit', [AdminController::class, 'edit'])->name('admin.users.edit');

    // РђРґРјРёРЅ С‚РѕР»СЊРєРѕ РґР»СЏ СЂРѕР»Рё "РђРґРјРёРЅРёСЃС‚СЂР°С‚РѕСЂ"
    Route::middleware('role:Администратор')
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {

            // РЎРѕР·РґР°РЅРёРµ РїРѕР»СЊР·РѕРІР°С‚РµР»СЏ
            Route::get('register', [AdminController::class, 'showRegister'])->name('register');
            Route::post('register', [AdminController::class, 'register'])->name('register.post');

            // РЎРѕС…СЂР°РЅРµРЅРёРµ РёР·РјРµРЅРµРЅРёР№ РїРѕР»СЊР·РѕРІР°С‚РµР»СЏ
            Route::patch('users/{user}', [AdminController::class, 'update'])->name('users.update');

            // Р‘Р»РѕРєРёСЂРѕРІРєР° / Р°РєС‚РёРІР°С†РёСЏ РїРѕР»СЊР·РѕРІР°С‚РµР»СЏ
            Route::patch('users/{user}/toggle', [AdminController::class, 'toggleActive'])
                ->name('users.toggle');

            // =========================
            // РЈРЎРўР РћР™РЎРўР’Рђ (РєРѕРЅС‚СЂРѕР»Р»РµСЂС‹ Рё РєР°РјРµСЂС‹)
            // =========================

            // СЃРїРёСЃРѕРє СѓСЃС‚СЂРѕР№СЃС‚РІ
            Route::get('devices', [DevicesController::class, 'index'])->name('devices.index');

            // Р»РѕРєР°С†РёРё
            Route::get('locations', [LocationsController::class, 'index'])->name('locations.index');
            Route::get('locations/create', [LocationsController::class, 'create'])->name('locations.create');
            Route::post('locations', [LocationsController::class, 'store'])->name('locations.store');
            Route::get('locations/{location}/edit', [LocationsController::class, 'edit'])->name('locations.edit');
            Route::put('locations/{location}', [LocationsController::class, 'update'])->name('locations.update');

            // СЃС‚СЂР°РЅРёС†Р° РґРѕР±Р°РІР»РµРЅРёСЏ
            Route::get('devices/create', [DevicesController::class, 'create'])->name('devices.create');

            // СЃРѕС…СЂР°РЅРµРЅРёРµ РЅРѕРІРѕРіРѕ СѓСЃС‚СЂРѕР№СЃС‚РІР°
            Route::post('devices', [DevicesController::class, 'store'])->name('devices.store');

            // СЃС‚СЂР°РЅРёС†Р° СЂРµРґР°РєС‚РёСЂРѕРІР°РЅРёСЏ СѓСЃС‚СЂРѕР№СЃС‚РІР° (РєРѕРЅС‚СЂРѕР»Р»РµСЂ / РєР°РјРµСЂР°)
            Route::get('devices/{type}/{id}/edit', [DevicesController::class, 'edit'])
                ->where('type', 'controller|camera')
                ->whereNumber('id')
                ->name('devices.edit');

            // СЃРѕС…СЂР°РЅРµРЅРёРµ РёР·РјРµРЅРµРЅРёР№ СѓСЃС‚СЂРѕР№СЃС‚РІР°
            Route::put('devices/{type}/{id}', [DevicesController::class, 'update'])
                ->where('type', 'controller|camera')
                ->whereNumber('id')
                ->name('devices.update');

            // Р±Р»РѕРєРёСЂРѕРІРєР° / СЂР°Р·Р±Р»РѕРєРёСЂРѕРІРєР° СѓСЃС‚СЂРѕР№СЃС‚РІР°
            Route::patch('devices/{type}/{id}/toggle', [DevicesController::class, 'toggle'])
                ->where('type', 'controller|camera')
                ->whereNumber('id')
                ->name('devices.toggle');

                Route::patch('/pairs/{id}/toggle', [DevicesController::class, 'pairsToggle'])
                    ->name('pairs.toggle');

            // =========================
            // РџРђР Р« РЈРЎРўР РћР™РЎРўР’
            // =========================

            // СЃС‚СЂР°РЅРёС†Р° СЃРѕР·РґР°РЅРёСЏ РїР°СЂС‹
            Route::get('device_pairs/create', [DevicesController::class, 'pairsCreate'])
                ->name('device_pairs.create');

            // СЃРѕС…СЂР°РЅРµРЅРёРµ РЅРѕРІРѕР№ РїР°СЂС‹
            Route::post('device_pairs', [DevicesController::class, 'pairsStore'])
                ->name('device_pairs.store');

            // СЃС‚СЂР°РЅРёС†Р° СЂРµРґР°РєС‚РёСЂРѕРІР°РЅРёСЏ РїР°СЂС‹
            Route::get('device_pairs/{id}/edit', [DevicesController::class, 'pairsEdit'])
                ->whereNumber('id')
                ->name('device_pairs.edit');

            // СЃРѕС…СЂР°РЅРµРЅРёРµ РёР·РјРµРЅРµРЅРёР№ РїР°СЂС‹
            Route::put('device_pairs/{id}', [DevicesController::class, 'pairsUpdate'])
                ->whereNumber('id')
                ->name('device_pairs.update');

            // Р±Р»РѕРєРёСЂРѕРІРєР° / СЂР°Р·Р±Р»РѕРєРёСЂРѕРІРєР° РїР°СЂС‹
            Route::patch('device_pairs/{id}/toggle', [DevicesController::class, 'pairsToggle'])
                ->whereNumber('id')
                ->name('device_pairs.toggle');

            
            //РќР°СЃС‚СЂРѕР№РєРё
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingsController::class, 'save'])->name('settings.save');
        Route::post('/updates/check', [UpdatesController::class, 'check'])->name('updates.check');
        Route::get('/updates/status', [UpdatesController::class, 'status'])->name('updates.status');
        Route::post('/updates/start', [UpdatesController::class, 'start'])->name('updates.start');
        Route::post('/updates/acknowledge-recovery', [UpdatesController::class, 'acknowledgeRecovery'])->name('updates.acknowledgeRecovery');
        Route::get('/logs', [ErrorLogController::class, 'index'])->name('logs.index');
        Route::post('/logs/test-error', [ErrorLogController::class, 'triggerTestError'])->name('logs.testError');

         Route::post('/settings/test-db', [SettingsController::class, 'testDb'])->name('settings.testDb');
         Route::post('/settings/test-storage', [SettingsController::class, 'testStorage'])->name('settings.testStorage');
         Route::post('/settings/license', [SettingsController::class, 'uploadLicense'])
    ->name('settings.license.upload');
         Route::get('/settings/device-pairs-api-token', [SettingsController::class, 'devicePairsApiToken'])
    ->name('settings.devicePairsApiToken');
         Route::post('/settings/device-pairs-api-token/generate', [SettingsController::class, 'generateDevicePairsApiToken'])
    ->name('settings.devicePairsApiToken.generate');
         Route::get('/settings/license-log', [SettingsController::class, 'downloadLicenseLog'])
    ->name('settings.licenseLog.download');

        });
});
