<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapInputName(SnakeCaseMapper::class)]
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

    /**
     * The column-keyed attributes to persist; omitted (Optional) fields are excluded.
     *
     * @return array<string, mixed>
     */
    public function forSaving(): array
    {
        return collect([
            'contract_number' => $this->contractNumber,
            'partner_name' => $this->partnerName,
            'description' => $this->description,
            'signed_at' => $this->signedAt,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
        ])->reject(fn (mixed $value): bool => $value instanceof Optional)->all();
    }
}
