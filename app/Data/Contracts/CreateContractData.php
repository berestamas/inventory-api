<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
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

    /**
     * The column-keyed attributes to persist.
     *
     * @return array<string, mixed>
     */
    public function forSaving(): array
    {
        return [
            'contract_number' => $this->contractNumber,
            'partner_name' => $this->partnerName,
            'description' => $this->description,
            'signed_at' => $this->signedAt,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
        ];
    }
}
