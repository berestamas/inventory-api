<?php

use App\Models\Contract;
use App\Models\Device;
use Illuminate\Support\Str;

test('a device can be attached to a contract', function (): void {
    $contract = Contract::factory()->create();
    $device = Device::factory()->create();

    $response = $this->postJson(route('contracts.devices.attach', $contract), [
        'device' => $device->uuid,
        'serial_number' => 'SN-0001',
    ])
        ->assertCreated()
        ->assertJsonPath('data.serialNumber', 'SN-0001')
        ->assertJsonPath('data.device.id', $device->uuid);

    $this->assertDatabaseHas('contract_device', [
        'contract_id' => $contract->id,
        'device_id' => $device->id,
        'serial_number' => 'SN-0001',
    ]);

    expect(array_keys($response->json('data')))->toBe(['serialNumber', 'attachedAt', 'device']);
});

test('the same device can be attached to one contract multiple times with different serials', function (): void {
    $contract = Contract::factory()->create();
    $device = Device::factory()->create();

    $this->postJson(route('contracts.devices.attach', $contract), [
        'device' => $device->uuid,
        'serial_number' => 'SN-0001',
    ])->assertCreated();

    $this->postJson(route('contracts.devices.attach', $contract), [
        'device' => $device->uuid,
        'serial_number' => 'SN-0002',
    ])->assertCreated();

    expect($contract->units()->count())->toBe(2);
});

test('the same device can be attached to different contracts', function (): void {
    $device = Device::factory()->create();
    [$first, $second] = Contract::factory()->count(2)->create();

    $this->postJson(route('contracts.devices.attach', $first), [
        'device' => $device->uuid,
        'serial_number' => 'SN-0001',
    ])->assertCreated();

    $this->postJson(route('contracts.devices.attach', $second), [
        'device' => $device->uuid,
        'serial_number' => 'SN-0002',
    ])->assertCreated();

    expect($device->units()->count())->toBe(2);
});

test('serial numbers are stored uppercase', function (): void {
    $contract = Contract::factory()->create();
    $device = Device::factory()->create();

    $this->postJson(route('contracts.devices.attach', $contract), [
        'device' => $device->uuid,
        'serial_number' => 'sn-abc-001',
    ])
        ->assertCreated()
        ->assertJsonPath('data.serialNumber', 'SN-ABC-001');

    $this->assertDatabaseHas('contract_device', ['serial_number' => 'SN-ABC-001']);
});

test('attaching to an unknown contract returns the 404 envelope', function (): void {
    $device = Device::factory()->create();

    $this->postJson(route('contracts.devices.attach', ['contract' => (string) Str::uuid7()]), [
        'device' => $device->uuid,
        'serial_number' => 'SN-0001',
    ])
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);
});

test('a device unit can be detached by serial number', function (): void {
    $contract = Contract::factory()->create();
    $device = Device::factory()->create();
    $contract->devices()->attach($device, ['serial_number' => 'SN-0001']);

    $this->deleteJson(route('contracts.devices.detach', [$contract, 'SN-0001']))
        ->assertNoContent();

    $this->assertDatabaseMissing('contract_device', ['serial_number' => 'SN-0001']);
    $this->assertDatabaseHas('contracts', ['id' => $contract->id]);
    $this->assertDatabaseHas('devices', ['id' => $device->id]);
});

test('detaching matches serial numbers case-insensitively', function (): void {
    $contract = Contract::factory()->create();
    $contract->devices()->attach(Device::factory()->create(), ['serial_number' => 'SN-ABC-001']);

    $this->deleteJson(route('contracts.devices.detach', [$contract, 'sn-abc-001']))
        ->assertNoContent();

    $this->assertDatabaseMissing('contract_device', ['serial_number' => 'SN-ABC-001']);
});

test('detaching a serial that belongs to another contract returns 404 and keeps the unit', function (): void {
    [$owner, $other] = Contract::factory()->count(2)->create();
    $owner->devices()->attach(Device::factory()->create(), ['serial_number' => 'SN-0001']);

    $this->deleteJson(route('contracts.devices.detach', [$other, 'SN-0001']))
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);

    $this->assertDatabaseHas('contract_device', ['serial_number' => 'SN-0001']);
});

test('detaching an unknown serial returns the 404 envelope', function (): void {
    $contract = Contract::factory()->create();

    $this->deleteJson(route('contracts.devices.detach', [$contract, 'SN-UNKNOWN']))
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);
});
