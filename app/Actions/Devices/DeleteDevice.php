<?php

declare(strict_types=1);

namespace App\Actions\Devices;

use App\Models\Device;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DeleteDevice
{
    /**
     * Delete a device type unless physical units of it are attached to a contract.
     *
     * The attachment guard is part of the DELETE statement itself, so a
     * concurrently attached unit can never slip between check and delete.
     * When nothing was deleted, distinguish "still attached" (409) from
     * "already deleted elsewhere" (idempotent no-op).
     */
    public function handle(Device $device): void
    {
        $deleted = Device::query()
            ->whereKey($device)
            ->whereDoesntHave('units')
            ->delete();

        if ($deleted > 0) {
            return;
        }

        throw_if(
            Device::query()->whereKey($device)->exists(),
            new ConflictHttpException('The device is attached to a contract and cannot be deleted.'),
        );
    }
}
