<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\FtpAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DevicesController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 1) Корень
Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('gallery', ['dir' => 'entrance_photos'])
        : redirect()->route('login');
})->name('root');

// 2) Гость: вход
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

    // Админ (просмотр/редактирование) — любой залогиненный
    
    Route::get('/admin/users',           [AdminController::class, 'users'])->name('admin.users');
    Route::get('/admin/users/{user}/edit', [AdminController::class, 'edit'])->name('admin.users.edit');

    // Админ только для роли "Администратор"
    Route::middleware('role:Администратор')
         ->prefix('admin')
         ->name('admin.')
         ->group(function() {
        // Создание пользователя
        Route::get  ('/register',        [AdminController::class, 'showRegister'])->name('register');
        Route::post ('/register',        [AdminController::class, 'register'])->name('register.post');

        // Сохранение изменений
        Route::patch('users/{user}',     [AdminController::class, 'update'])->name('users.update');

        // Блокировка / активация
        Route::patch('users/{user}/toggle', [AdminController::class, 'toggleActive'])
             ->name('users.toggle');

          // список устройств
    Route::get('devices', [DevicesController::class, 'index'])->name('devices.index');

    // страница добавления
    Route::get('devices/create', [DevicesController::class, 'create'])->name('devices.create');

    // сохранение нового устройства
    Route::post('devices', [DevicesController::class, 'store'])->name('devices.store');

        // страница редактирования устройства (контроллер / камера)
    Route::get('devices/{type}/{id}/edit', [DevicesController::class, 'edit'])
        ->where('type', 'controller|camera')
        ->name('devices.edit');

    // сохранение изменений устройства
    Route::patch('devices/{type}/{id}', [DevicesController::class, 'update'])
        ->where('type', 'controller|camera')
        ->name('devices.update');

    // блокировка / разблокировка устройства
    Route::patch('devices/{type}/{id}/toggle', [DevicesController::class, 'toggle'])
        ->where('type', 'controller|camera')
        ->name('devices.toggle');

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
Route::patch('device_pairs/{id}', [DevicesController::class, 'pairsUpdate'])
    ->whereNumber('id')
    ->name('device_pairs.update');

// блокировка / разблокировка пары
Route::patch('device_pairs/{id}/toggle', [DevicesController::class, 'pairsToggle'])
    ->whereNumber('id')
    ->name('device_pairs.toggle');
    });


   
});
