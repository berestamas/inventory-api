<?php

use App\Actions\Devices\CreateDevice;
use App\Data\Devices\CreateDeviceData;
use App\Enums\DeviceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('device creation persists the device with a generated uuid', function (): void {
    $device = app(CreateDevice::class)->handle(new CreateDeviceData(
        name: 'ThinkPad X1 Carbon',
        manufacturer: 'Lenovo',
        category: DeviceCategory::Laptop,
        description: 'A business laptop.',
    ));

    expect($device->exists)->toBeTrue()
        ->and($device->uuid)->toBeString()->toHaveLength(36)
        ->and($device->name)->toBe('ThinkPad X1 Carbon')
        ->and($device->manufacturer)->toBe('Lenovo')
        ->and($device->category)->toBe(DeviceCategory::Laptop)
        ->and($device->description)->toBe('A business laptop.');
});

test('device creation allows a missing description', function (): void {
    $device = app(CreateDevice::class)->handle(new CreateDeviceData(
        name: 'ThinkPad X1 Carbon',
        manufacturer: 'Lenovo',
        category: DeviceCategory::Laptop,
    ));

    expect($device->description)->toBeNull();
});
