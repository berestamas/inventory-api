<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\DeviceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::apiResource('devices', DeviceController::class)->whereUuid('device');
    Route::apiResource('contracts', ContractController::class)->whereUuid('contract');
});
