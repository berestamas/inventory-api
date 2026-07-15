<?php

declare(strict_types=1);

namespace App\Data\Devices;

use App\Enums\DeviceCategory;
use Spatie\LaravelData\Data;

class CreateDeviceData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $manufacturer,
        public readonly DeviceCategory $category,
        public readonly ?string $description = null,
    ) {}
}
