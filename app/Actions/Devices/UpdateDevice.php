<?php

declare(strict_types=1);

namespace App\Actions\Devices;

use App\Data\Devices\UpdateDeviceData;
use App\Models\Device;

class UpdateDevice
{
    /**
     * Update a device type; omitted (Optional) fields are left untouched.
     */
    public function handle(Device $device, UpdateDeviceData $updateDeviceData): Device
    {
        $device->update($updateDeviceData->toArray());

        return $device;
    }
}
