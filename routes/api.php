<?php

use App\Http\Controllers\Api\DevicePairApiController;
use App\Http\Controllers\Api\SystemDiagnosticsApiController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SettingsApiController;
use App\Http\Controllers\Api\UsersApiController;

// The RouteServiceProvider already applies the `api` middleware group to this file.
// Keep API-specific concerns here so throttling and bindings run exactly once.
Route::middleware(['request.id', 'api.token', 'api.audit'])->group(function (): void {
    Route::get('device-pairs/active', [DevicePairApiController::class, 'active']);
    Route::get('settings', [SettingsApiController::class, 'show']);
    Route::get('settings/table', [SettingsApiController::class, 'tableIndex']);
    Route::put('settings/table', [SettingsApiController::class, 'tableUpsert']);
    Route::get('users', [UsersApiController::class, 'index']);
    Route::post('users/auth', [UsersApiController::class, 'auth']);

    Route::get('system/notifications/status', [SystemDiagnosticsApiController::class, 'channelsStatus']);
    Route::post('system/notifications/test-email', [SystemDiagnosticsApiController::class, 'testEmail']);
});

