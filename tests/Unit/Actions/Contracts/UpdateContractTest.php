<?php

use App\Actions\Contracts\UpdateContract;
use App\Data\Contracts\UpdateContractData;
use App\Models\Contract;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('contract update changes the provided fields', function (): void {
    $contract = Contract::factory()->create();

    app(UpdateContract::class)->handle($contract, new UpdateContractData(
        contractNumber: 'CTR-NEW',
        partnerName: 'New Partner',
        endsAt: CarbonImmutable::parse('2033-06-30'),
    ));

    expect($contract->refresh())
        ->contract_number->toBe('CTR-NEW')
        ->partner_name->toBe('New Partner')
        ->and($contract->ends_at?->toDateString())->toBe('2033-06-30');
});

test('contract update leaves omitted fields untouched', function (): void {
    $contract = Contract::factory()->create(['partner_name' => 'Old Partner']);
    $originalNumber = $contract->contract_number;

    app(UpdateContract::class)->handle($contract, new UpdateContractData(partnerName: 'New Partner'));

    expect($contract->refresh())
        ->partner_name->toBe('New Partner')
        ->contract_number->toBe($originalNumber);
});

test('contract update can clear nullable dates', function (): void {
    $contract = Contract::factory()->create();

    app(UpdateContract::class)->handle($contract, new UpdateContractData(
        signedAt: null,
        startsAt: null,
        endsAt: null,
    ));

    expect($contract->refresh())
        ->signed_at->toBeNull()
        ->starts_at->toBeNull()
        ->ends_at->toBeNull();
});
