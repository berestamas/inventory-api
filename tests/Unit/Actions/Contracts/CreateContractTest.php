<?php

use App\Actions\Contracts\CreateContract;
use App\Data\Contracts\CreateContractData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('contract creation persists the contract with a generated uuid', function (): void {
    $contract = app(CreateContract::class)->handle(new CreateContractData(
        contractNumber: 'CTR-2026-001',
        partnerName: 'Acme Kft.',
        description: 'Hardware leasing contract.',
        signedAt: CarbonImmutable::parse('2026-01-01'),
        startsAt: CarbonImmutable::parse('2026-01-15'),
        endsAt: CarbonImmutable::parse('2028-01-15'),
    ));

    expect($contract->exists)->toBeTrue()
        ->and($contract->uuid)->toBeString()->toHaveLength(36)
        ->and($contract->contract_number)->toBe('CTR-2026-001')
        ->and($contract->partner_name)->toBe('Acme Kft.')
        ->and($contract->starts_at?->toDateString())->toBe('2026-01-15');
});

test('contract creation allows missing optional fields', function (): void {
    $contract = app(CreateContract::class)->handle(new CreateContractData(
        contractNumber: 'CTR-2026-002',
        partnerName: 'Acme Kft.',
    ));

    expect($contract->description)->toBeNull()
        ->and($contract->signed_at)->toBeNull()
        ->and($contract->starts_at)->toBeNull()
        ->and($contract->ends_at)->toBeNull();
});
