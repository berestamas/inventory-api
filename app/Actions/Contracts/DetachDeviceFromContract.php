<?php

declare(strict_types=1);

namespace App\Actions\Contracts;

use App\Models\Contract;

class DetachDeviceFromContract
{
    /**
     * Detach the unit with the given serial number from the contract.
     *
     * Throws a RecordsNotFoundException (rendered as 404) when the serial is
     * unknown or belongs to a different contract.
     */
    public function handle(Contract $contract, string $serialNumber): void
    {
        $contract->units()
            ->where('serial_number', mb_strtoupper($serialNumber))
            ->sole()
            ->delete();
    }
}
