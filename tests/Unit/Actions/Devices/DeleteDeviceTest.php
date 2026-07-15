<?php

use App\Actions\Devices\DeleteDevice;
use App\Models\Contract;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('device deletion removes an unattached device', function (): void {
    $device = Device::factory()->create();

    app(DeleteDevice::class)->handle($device);

    $this->assertDatabaseMissing('devices', ['id' => $device->id]);
});

test('device deletion refuses an attached device and keeps it', function (): void {
    $device = Device::factory()->create();
    $contract = Contract::factory()->create();
    $contract->devices()->attach($device, ['serial_number' => 'SN-00000001']);

    expect(fn () => app(DeleteDevice::class)->handle($device))
        ->toThrow(ConflictHttpException::class);

    $this->assertDatabaseHas('devices', ['id' => $device->id]);
});
