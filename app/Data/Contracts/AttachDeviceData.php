<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class AttachDeviceData extends Data
{
    public function __construct(
        #[MapInputName('device')]
        public readonly string $deviceUuid,
        #[MapInputName('serial_number')]
        public readonly string $serialNumber,
    ) {}
}
