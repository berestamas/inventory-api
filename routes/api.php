<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\ContractDeviceController;
use App\Http\Controllers\Api\DeviceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::apiResource('devices', DeviceController::class)->whereUuid('device');
    Route::apiResource('contracts', ContractController::class)->whereUuid('contract');

    Route::post('contracts/{contract}/devices', [ContractDeviceController::class, 'store'])
        ->whereUuid('contract')
        ->name('contracts.devices.attach');
    Route::delete('contracts/{contract}/devices/{serialNumber}', [ContractDeviceController::class, 'destroy'])
        ->whereUuid('contract')
        ->where('serialNumber', '[A-Za-z0-9._:\-]+')
        ->name('contracts.devices.detach');
});
