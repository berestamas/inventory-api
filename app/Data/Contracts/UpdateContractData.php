<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
class UpdateContractData extends Data
{
    public function __construct(
        public readonly string|Optional $contractNumber = new Optional,
        public readonly string|Optional $partnerName = new Optional,
        public readonly string|Optional|null $description = new Optional,
        public readonly CarbonImmutable|Optional|null $signedAt = new Optional,
        public readonly CarbonImmutable|Optional|null $startsAt = new Optional,
        public readonly CarbonImmutable|Optional|null $endsAt = new Optional,
    ) {}
}
