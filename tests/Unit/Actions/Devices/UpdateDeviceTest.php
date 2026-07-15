<?php

use App\Actions\Devices\UpdateDevice;
use App\Data\Devices\UpdateDeviceData;
use App\Enums\DeviceCategory;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('device update changes the provided fields', function (): void {
    $device = Device::factory()->create();

    app(UpdateDevice::class)->handle($device, new UpdateDeviceData(
        name: 'New name',
        manufacturer: 'HP',
        category: DeviceCategory::Printer,
        description: 'New description.',
    ));

    expect($device->refresh())
        ->name->toBe('New name')
        ->manufacturer->toBe('HP')
        ->category->toBe(DeviceCategory::Printer)
        ->description->toBe('New description.');
});

test('device update leaves omitted fields untouched', function (): void {
    $device = Device::factory()->create(['name' => 'Old name', 'manufacturer' => 'Dell']);

    app(UpdateDevice::class)->handle($device, new UpdateDeviceData(name: 'New name'));

    expect($device->refresh())
        ->name->toBe('New name')
        ->manufacturer->toBe('Dell');
});
