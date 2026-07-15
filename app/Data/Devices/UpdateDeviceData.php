<?php

declare(strict_types=1);

namespace App\Data\Devices;

use App\Enums\DeviceCategory;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateDeviceData extends Data
{
    public function __construct(
        public readonly string|Optional $name = new Optional,
        public readonly string|Optional $manufacturer = new Optional,
        public readonly DeviceCategory|Optional $category = new Optional,
        public readonly string|Optional|null $description = new Optional,
    ) {}
}
