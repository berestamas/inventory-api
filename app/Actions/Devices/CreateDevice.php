<?php

declare(strict_types=1);

namespace App\Actions\Devices;

use App\Data\Devices\CreateDeviceData;
use App\Models\Device;

class CreateDevice
{
    /**
     * Create a new device type.
     */
    public function handle(CreateDeviceData $createDeviceData): Device
    {
        return Device::create([
            'name' => $createDeviceData->name,
            'manufacturer' => $createDeviceData->manufacturer,
            'category' => $createDeviceData->category,
            'description' => $createDeviceData->description,
        ]);
    }
}
