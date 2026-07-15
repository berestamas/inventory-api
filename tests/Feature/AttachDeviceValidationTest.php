<?php

use App\Models\Contract;
use App\Models\ContractDevice;
use App\Models\Device;
use Illuminate\Support\Str;

test('device attachment rejects invalid fields', function (string $field, mixed $value): void {
    $contract = Contract::factory()->create();
    $device = Device::factory()->create();

    $this->postJson(route('contracts.devices.attach', $contract), [
        'device' => $device->uuid,
        'serial_number' => 'SN-0001',
        $field => $value,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect(ContractDevice::query()->count())->toBe(0);
})->with('invalid attach fields');

test('device attachment rejects missing required fields', function (string $field): void {
    $contract = Contract::factory()->create();
    $device = Device::factory()->create();

    $payload = [
        'device' => $device->uuid,
        'serial_number' => 'SN-0001',
    ];
    unset($payload[$field]);

    $this->postJson(route('contracts.devices.attach', $contract), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect(ContractDevice::query()->count())->toBe(0);
})->with('required attach fields');

test('device attachment accepts boundary serials', function (string $field, mixed $value): void {
    $contract = Contract::factory()->create();
    $device = Device::factory()->create();

    $this->postJson(route('contracts.devices.attach', $contract), [
        'device' => $device->uuid,
        $field => $value,
    ])
        ->assertCreated();

    expect(ContractDevice::query()->count())->toBe(1);
})->with('valid attach serial boundaries');

test('device attachment rejects an unknown device uuid with 422', function (): void {
    $contract = Contract::factory()->create();

    $this->postJson(route('contracts.devices.attach', $contract), [
        'device' => (string) Str::uuid7(),
        'serial_number' => 'SN-0001',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['device']);

    expect(ContractDevice::query()->count())->toBe(0);
});

test('device attachment rejects an already used serial number', function (): void {
    $contract = Contract::factory()->create();
    $device = Device::factory()->create();
    $contract->devices()->attach($device, ['serial_number' => 'SN-0001']);

    $this->postJson(route('contracts.devices.attach', $contract), [
        'device' => $device->uuid,
        'serial_number' => 'SN-0001',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['serial_number']);

    expect(ContractDevice::query()->count())->toBe(1);
});

test('device attachment rejects a case variant of a used serial number', function (): void {
    $contract = Contract::factory()->create();
    $device = Device::factory()->create();
    $contract->devices()->attach($device, ['serial_number' => 'SN-0001']);

    $this->postJson(route('contracts.devices.attach', $contract), [
        'device' => $device->uuid,
        'serial_number' => 'sn-0001',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['serial_number']);

    expect(ContractDevice::query()->count())->toBe(1);
});
