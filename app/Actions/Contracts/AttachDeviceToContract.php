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
     * Serial numbers are stored uppercase so uniqueness behaves identically
     * on case-insensitive (MySQL) and case-sensitive (SQLite) engines.
     */
    public function handle(Contract $contract, AttachDeviceData $attachDeviceData): ContractDevice
    {
        $device = Device::query()->where('uuid', $attachDeviceData->deviceUuid)->sole();

        $contractDevice = new ContractDevice([
            'serial_number' => mb_strtoupper($attachDeviceData->serialNumber),
        ]);
        $contractDevice->contract()->associate($contract);
        $contractDevice->device()->associate($device);
        $contractDevice->save();

        return $contractDevice;
    }
}
