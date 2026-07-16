<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use App\Models\ContractDevice;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
class AttachDeviceData extends Data
{
    public function __construct(
        #[MapInputName('device')]
        public readonly string $deviceUuid,
        public readonly string $serialNumber,
    ) {}

    /**
     * The column-keyed attributes to persist, with the serial in canonical form.
     *
     * @return array<string, mixed>
     */
    public function forSaving(): array
    {
        return [
            'serial_number' => ContractDevice::canonicalSerial($this->serialNumber),
        ];
    }
}
