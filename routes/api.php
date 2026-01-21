<?php

use App\Http\Controllers\Api\DevicePairApiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'api.token'])->get('device-pairs/active', [DevicePairApiController::class, 'active']);
