<?php

use App\Models\Contract;

/**
 * @return array<string, mixed>
 */
function validContractPayload(): array
{
    return [
        'contract_number' => 'CTR-2026-001',
        'partner_name' => 'Acme Kft.',
        'description' => 'Hardware leasing contract.',
        'signed_at' => '2026-01-01',
        'starts_at' => '2026-01-15',
        'ends_at' => '2028-01-15',
    ];
}

test('contract creation rejects invalid fields', function (string $field, mixed $value): void {
    $this->postJson(route('contracts.store'), [...validContractPayload(), $field => $value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect(Contract::query()->count())->toBe(0);
})->with('invalid contract fields');

test('contract creation rejects missing required fields', function (string $field): void {
    $payload = validContractPayload();
    unset($payload[$field]);

    $this->postJson(route('contracts.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect(Contract::query()->count())->toBe(0);
})->with('required contract fields');

test('contract creation rejects a duplicate contract number', function (): void {
    $existing = Contract::factory()->create();

    $this->postJson(route('contracts.store'), [...validContractPayload(), 'contract_number' => $existing->contract_number])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['contract_number']);

    expect(Contract::query()->count())->toBe(1);
});

test('contract creation rejects an end date without a start date', function (): void {
    $payload = validContractPayload();
    unset($payload['starts_at']);

    $this->postJson(route('contracts.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['starts_at']);

    expect(Contract::query()->count())->toBe(0);
});

test('contract creation accepts boundary values', function (string $field, mixed $value): void {
    $this->postJson(route('contracts.store'), [...validContractPayload(), $field => $value])
        ->assertCreated();

    expect(Contract::query()->count())->toBe(1);
})->with('valid contract field boundaries');

test('contract update rejects invalid fields', function (string $field, mixed $value): void {
    $contract = Contract::factory()->create([
        'starts_at' => '2030-01-01',
        'ends_at' => '2032-01-01',
    ]);
    $original = $contract->refresh()->getAttributes();

    $this->patchJson(route('contracts.update', $contract), [$field => $value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect($contract->refresh()->getAttributes())->toBe($original);
})->with('invalid contract fields');

test("contract update rejects another contract's number", function (): void {
    $other = Contract::factory()->create();
    $contract = Contract::factory()->create();

    $this->patchJson(route('contracts.update', $contract), ['contract_number' => $other->contract_number])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['contract_number']);

    expect($contract->refresh()->contract_number)->not->toBe($other->contract_number);
});

test('contract update accepts its own contract number', function (): void {
    $contract = Contract::factory()->create();

    $this->patchJson(route('contracts.update', $contract), ['contract_number' => $contract->contract_number])
        ->assertSuccessful();
});

test('contract update rejects an end date before the stored start date', function (): void {
    $contract = Contract::factory()->create([
        'starts_at' => '2030-01-01',
        'ends_at' => '2032-01-01',
    ]);

    $this->patchJson(route('contracts.update', $contract), ['ends_at' => '2029-12-31'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ends_at']);

    expect($contract->refresh()->ends_at?->toDateString())->toBe('2032-01-01');
});

test('contract update accepts an end date after the stored start date', function (): void {
    $contract = Contract::factory()->create([
        'starts_at' => '2030-01-01',
        'ends_at' => '2032-01-01',
    ]);

    $this->patchJson(route('contracts.update', $contract), ['ends_at' => '2033-01-01'])
        ->assertSuccessful();

    expect($contract->refresh()->ends_at?->toDateString())->toBe('2033-01-01');
});
