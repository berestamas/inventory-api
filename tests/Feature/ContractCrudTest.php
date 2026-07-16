<?php

use App\Models\Contract;
use App\Models\Device;
use Illuminate\Support\Str;

test('contracts can be listed with pagination', function (): void {
    Contract::factory()->count(30)->create();

    $this->getJson(route('contracts.index'))
        ->assertSuccessful()
        ->assertJsonCount(25, 'data')
        ->assertJsonPath('meta.total', 30);
});

test('contract listing does not include devices', function (): void {
    $contract = Contract::factory()->create();
    $contract->devices()->attach(Device::factory()->create(), ['serial_number' => 'SN-00000001']);

    $response = $this->getJson(route('contracts.index'))->assertSuccessful();

    expect($response->json('data.0'))->not->toHaveKey('devices');
});

test('contracts can be filtered by contract number', function (): void {
    Contract::factory()->create(['contract_number' => 'CTR-2026-ALPHA']);
    Contract::factory()->create(['contract_number' => 'CTR-2026-BETA']);

    $this->getJson(route('contracts.index', ['filter' => ['contract_number' => 'alpha']]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.contractNumber', 'CTR-2026-ALPHA');
});

test('contracts can be filtered by partner name', function (): void {
    Contract::factory()->create(['partner_name' => 'Acme Kft.']);
    Contract::factory()->create(['partner_name' => 'Globex Zrt.']);

    $this->getJson(route('contracts.index', ['filter' => ['partner_name' => 'acme']]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.partnerName', 'Acme Kft.');
});

test('contracts can be sorted by contract number', function (): void {
    Contract::factory()->create(['contract_number' => 'CTR-B']);
    Contract::factory()->create(['contract_number' => 'CTR-A']);

    $this->getJson(route('contracts.index', ['sort' => 'contract_number']))
        ->assertSuccessful()
        ->assertJsonPath('data.0.contractNumber', 'CTR-A');
});

test('contract listing rejects non-whitelisted filters', function (): void {
    $this->getJson(route('contracts.index', ['filter' => ['id' => 1]]))
        ->assertBadRequest();
});

test('a contract can be shown with its attached device units', function (): void {
    $contract = Contract::factory()->create();
    $device = Device::factory()->create();
    $contract->devices()->attach($device, ['serial_number' => 'SN-00000001']);
    $contract->devices()->attach($device, ['serial_number' => 'SN-00000002']);

    $response = $this->getJson(route('contracts.show', $contract))
        ->assertSuccessful()
        ->assertJsonPath('data.id', $contract->uuid)
        ->assertJsonCount(2, 'data.devices');

    expect(collect($response->json('data.devices'))->pluck('serialNumber')->all())
        ->toBe(['SN-00000001', 'SN-00000002'])
        ->and($response->json('data.devices.0.device.id'))->toBe($device->uuid);
});

test('contract responses expose exactly the allowed fields', function (): void {
    $contract = Contract::factory()->create();
    $contract->devices()->attach(Device::factory()->create(), ['serial_number' => 'SN-00000001']);

    $response = $this->getJson(route('contracts.show', $contract))->assertSuccessful();

    expect(array_keys($response->json('data')))->toBe([
        'id', 'contractNumber', 'partnerName', 'description',
        'signedAt', 'startsAt', 'endsAt', 'devices', 'createdAt', 'updatedAt',
    ])->and(array_keys($response->json('data.devices.0')))->toBe([
        'serialNumber', 'attachedAt', 'device',
    ]);
});

test('showing an unknown contract returns the 404 envelope', function (): void {
    $this->getJson(route('contracts.show', ['contract' => (string) Str::uuid7()]))
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);
});

test('contracts can be created', function (): void {
    $response = $this->postJson(route('contracts.store'), [
        'contract_number' => 'CTR-2026-001',
        'partner_name' => 'Acme Kft.',
        'description' => 'Hardware leasing contract.',
        'signed_at' => '2026-01-01',
        'starts_at' => '2026-01-15',
        'ends_at' => '2028-01-15',
    ])
        ->assertCreated()
        ->assertJsonPath('data.contractNumber', 'CTR-2026-001')
        ->assertJsonPath('data.startsAt', '2026-01-15');

    $this->assertDatabaseHas('contracts', [
        'contract_number' => 'CTR-2026-001',
        'partner_name' => 'Acme Kft.',
    ]);

    expect($response->json('data.id'))->toBeString()->toHaveLength(36);
});

test('contracts can be created with only the required fields', function (): void {
    $this->postJson(route('contracts.store'), [
        'contract_number' => 'CTR-2026-002',
        'partner_name' => 'Acme Kft.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.signedAt', null);

    $this->assertDatabaseHas('contracts', ['contract_number' => 'CTR-2026-002']);
});

test('contracts can be partially updated', function (): void {
    $contract = Contract::factory()->create(['partner_name' => 'Old Partner']);
    $originalNumber = $contract->contract_number;

    $this->patchJson(route('contracts.update', $contract), ['partner_name' => 'New Partner'])
        ->assertSuccessful()
        ->assertJsonPath('data.partnerName', 'New Partner')
        ->assertJsonPath('data.contractNumber', $originalNumber);

    expect($contract->refresh())
        ->partner_name->toBe('New Partner')
        ->contract_number->toBe($originalNumber);
});

test('updating an unknown contract returns the 404 envelope', function (): void {
    $this->patchJson(route('contracts.update', ['contract' => (string) Str::uuid7()]), ['partner_name' => 'New Partner'])
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);
});

test('contracts can be deleted together with their units', function (): void {
    $contract = Contract::factory()->create();
    $device = Device::factory()->create();
    $contract->devices()->attach($device, ['serial_number' => 'SN-00000001']);

    $this->deleteJson(route('contracts.destroy', $contract))->assertNoContent();

    $this->assertDatabaseMissing('contracts', ['id' => $contract->id]);
    $this->assertDatabaseMissing('contract_device', ['serial_number' => 'SN-00000001']);
    $this->assertDatabaseHas('devices', ['id' => $device->id]);
});

test('deleting an unknown contract returns the 404 envelope', function (): void {
    $this->deleteJson(route('contracts.destroy', ['contract' => (string) Str::uuid7()]))
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);
});
