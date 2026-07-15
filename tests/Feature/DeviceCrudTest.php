<?php

use App\Enums\DeviceCategory;
use App\Models\Contract;
use App\Models\Device;
use Illuminate\Support\Str;

test('devices can be listed with pagination', function (): void {
    Device::factory()->count(30)->create();

    $this->getJson(route('devices.index'))
        ->assertSuccessful()
        ->assertJsonCount(25, 'data')
        ->assertJsonPath('meta.total', 30);
});

test('devices can be filtered by name', function (): void {
    Device::factory()->create(['name' => 'ThinkPad X1']);
    Device::factory()->create(['name' => 'MacBook Pro']);

    $this->getJson(route('devices.index', ['filter' => ['name' => 'think']]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'ThinkPad X1');
});

test('devices can be filtered by exact category', function (): void {
    Device::factory()->ofCategory(DeviceCategory::Laptop)->create();
    Device::factory()->ofCategory(DeviceCategory::Monitor)->create();

    $this->getJson(route('devices.index', ['filter' => ['category' => DeviceCategory::Laptop->value]]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.category', DeviceCategory::Laptop->value);
});

test('devices can be sorted by name', function (): void {
    Device::factory()->create(['name' => 'Zebra Printer']);
    Device::factory()->create(['name' => 'Alpha Router']);

    $this->getJson(route('devices.index', ['sort' => 'name']))
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'Alpha Router');
});

test('device listing rejects non-whitelisted filters', function (): void {
    $this->getJson(route('devices.index', ['filter' => ['id' => 1]]))
        ->assertBadRequest();
});

test('device listing rejects non-whitelisted sorts', function (): void {
    $this->getJson(route('devices.index', ['sort' => 'id']))
        ->assertBadRequest();
});

test('a device can be shown by uuid', function (): void {
    $device = Device::factory()->create();

    $this->getJson(route('devices.show', $device))
        ->assertSuccessful()
        ->assertJsonPath('data.id', $device->uuid);
});

test('device responses expose exactly the allowed fields', function (): void {
    $device = Device::factory()->create();

    $response = $this->getJson(route('devices.show', $device))->assertSuccessful();

    expect(array_keys($response->json('data')))->toBe([
        'id', 'name', 'manufacturer', 'category', 'description', 'created_at', 'updated_at',
    ]);
});

test('showing an unknown device returns the 404 envelope', function (): void {
    $this->getJson(route('devices.show', ['device' => (string) Str::uuid7()]))
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);
});

test('devices can be created', function (): void {
    $response = $this->postJson(route('devices.store'), [
        'name' => 'ThinkPad X1 Carbon',
        'manufacturer' => 'Lenovo',
        'category' => DeviceCategory::Laptop->value,
        'description' => 'A business laptop.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'ThinkPad X1 Carbon');

    $this->assertDatabaseHas('devices', [
        'name' => 'ThinkPad X1 Carbon',
        'manufacturer' => 'Lenovo',
        'category' => DeviceCategory::Laptop->value,
    ]);

    expect($response->json('data.id'))->toBeString()->toHaveLength(36);
});

test('devices can be partially updated', function (): void {
    $device = Device::factory()->create(['name' => 'Old name', 'manufacturer' => 'Dell']);

    $this->patchJson(route('devices.update', $device), ['name' => 'New name'])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'New name')
        ->assertJsonPath('data.manufacturer', 'Dell');

    expect($device->refresh())
        ->name->toBe('New name')
        ->manufacturer->toBe('Dell');
});

test('updating an unknown device returns the 404 envelope', function (): void {
    $this->patchJson(route('devices.update', ['device' => (string) Str::uuid7()]), ['name' => 'New name'])
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.']);
});

test('devices can be deleted', function (): void {
    $device = Device::factory()->create();

    $this->deleteJson(route('devices.destroy', $device))->assertNoContent();

    $this->assertDatabaseMissing('devices', ['id' => $device->id]);
});

test('an attached device cannot be deleted', function (): void {
    $device = Device::factory()->create();
    $contract = Contract::factory()->create();
    $contract->devices()->attach($device, ['serial_number' => 'SN-00000001']);

    $this->deleteJson(route('devices.destroy', $device))
        ->assertConflict()
        ->assertJsonPath('message', 'The device is attached to a contract and cannot be deleted.');

    $this->assertDatabaseHas('devices', ['id' => $device->id]);
});
