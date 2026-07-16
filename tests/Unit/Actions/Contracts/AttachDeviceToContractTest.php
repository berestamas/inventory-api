<?php

use App\Actions\Contracts\AttachDeviceToContract;
use App\Data\Contracts\AttachDeviceData;
use App\Models\Contract;
use App\Models\Device;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('device attachment creates a unit with an uppercased serial', function (): void {
    $contract = Contract::factory()->create();
    $device = Device::factory()->create();

    $contractDevice = app(AttachDeviceToContract::class)->handle($contract, new AttachDeviceData(
        deviceUuid: $device->uuid,
        serialNumber: 'sn-abc-001',
    ));

    expect($contractDevice->exists)->toBeTrue()
        ->and($contractDevice->serial_number)->toBe('SN-ABC-001')
        ->and($contractDevice->contract()->is($contract))->toBeTrue()
        ->and($contractDevice->device()->is($device))->toBeTrue();
});

test('device attachment allows the same device twice with different serials', function (): void {
    $contract = Contract::factory()->create();
    $device = Device::factory()->create();
    $attachDeviceToContract = app(AttachDeviceToContract::class);

    $attachDeviceToContract->handle($contract, new AttachDeviceData($device->uuid, 'SN-0001'));
    $attachDeviceToContract->handle($contract, new AttachDeviceData($device->uuid, 'SN-0002'));

    expect($contract->units()->count())->toBe(2);
});

test('device attachment fails for an unknown device uuid and creates nothing', function (): void {
    $contract = Contract::factory()->create();

    expect(fn () => app(AttachDeviceToContract::class)->handle($contract, new AttachDeviceData(
        deviceUuid: (string) Str::uuid7(),
        serialNumber: 'SN-0001',
    )))->toThrow(ModelNotFoundException::class);

    expect($contract->units()->count())->toBe(0);
});
