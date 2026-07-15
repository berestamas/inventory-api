<?php

use App\Enums\DeviceCategory;
use App\Models\Device;

/**
 * @return array<string, mixed>
 */
function validDevicePayload(): array
{
    return [
        'name' => 'ThinkPad X1 Carbon',
        'manufacturer' => 'Lenovo',
        'category' => DeviceCategory::Laptop->value,
        'description' => 'A 14-inch business laptop.',
    ];
}

test('device creation rejects invalid fields', function (string $field, mixed $value): void {
    $this->postJson(route('devices.store'), [...validDevicePayload(), $field => $value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect(Device::query()->count())->toBe(0);
})->with('invalid device fields');

test('device creation rejects missing required fields', function (string $field): void {
    $payload = validDevicePayload();
    unset($payload[$field]);

    $this->postJson(route('devices.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect(Device::query()->count())->toBe(0);
})->with('required device fields');

test('device creation accepts boundary values', function (string $field, mixed $value): void {
    $this->postJson(route('devices.store'), [...validDevicePayload(), $field => $value])
        ->assertCreated();

    expect(Device::query()->count())->toBe(1);
})->with('valid device field boundaries');

test('device update rejects invalid fields', function (string $field, mixed $value): void {
    $device = Device::factory()->create();
    $original = $device->refresh()->getAttributes();

    $this->patchJson(route('devices.update', $device), [$field => $value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect($device->refresh()->getAttributes())->toBe($original);
})->with('invalid device fields');

test('device update accepts boundary values', function (string $field, mixed $value): void {
    $device = Device::factory()->create();

    $this->patchJson(route('devices.update', $device), [$field => $value])
        ->assertSuccessful();
})->with('valid device field boundaries');
