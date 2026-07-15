<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
class CreateContractData extends Data
{
    public function __construct(
        public readonly string $contractNumber,
        public readonly string $partnerName,
        public readonly ?string $description = null,
        public readonly ?CarbonImmutable $signedAt = null,
        public readonly ?CarbonImmutable $startsAt = null,
        public readonly ?CarbonImmutable $endsAt = null,
    ) {}
}
