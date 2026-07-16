<?php

declare(strict_types=1);

namespace App\Actions\Contracts;

use App\Data\Contracts\AttachDeviceData;
use App\Models\Contract;
use App\Models\ContractDevice;
use App\Models\Device;

class AttachDeviceToContract
{
    /**
     * Attach a physical unit of a device type to a contract.
     *
     * forSaving() yields the canonical serial form on every entry point;
     * AttachDeviceRequest applies the same canonicalization earlier so the
     * unique rule already sees it.
     */
    public function handle(Contract $contract, AttachDeviceData $attachDeviceData): ContractDevice
    {
        $device = Device::query()->where('uuid', $attachDeviceData->deviceUuid)->sole();

        $contractDevice = new ContractDevice($attachDeviceData->forSaving());
        $contractDevice->contract()->associate($contract);
        $contractDevice->device()->associate($device);
        $contractDevice->saveOrFail();

        return $contractDevice;
    }
}
