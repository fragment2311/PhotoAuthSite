<?php

use App\Http\Controllers\Api\DevicePairApiController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SettingsApiController;

Route::middleware(['api', 'api.token'])->get('device-pairs/active', [DevicePairApiController::class, 'active']);
Route::middleware(['api', 'api.token'])->get('settings', [SettingsApiController::class, 'show']);