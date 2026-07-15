<?php

use App\Actions\Contracts\DetachDeviceFromContract;
use App\Models\Contract;
use App\Models\Device;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('device detachment removes the unit by serial number', function (): void {
    $contract = Contract::factory()->create();
    $contract->devices()->attach(Device::factory()->create(), ['serial_number' => 'SN-0001']);

    app(DetachDeviceFromContract::class)->handle($contract, 'SN-0001');

    $this->assertDatabaseMissing('contract_device', ['serial_number' => 'SN-0001']);
});

test('device detachment matches serials case-insensitively', function (): void {
    $contract = Contract::factory()->create();
    $contract->devices()->attach(Device::factory()->create(), ['serial_number' => 'SN-ABC-001']);

    app(DetachDeviceFromContract::class)->handle($contract, 'sn-abc-001');

    $this->assertDatabaseMissing('contract_device', ['serial_number' => 'SN-ABC-001']);
});

test('device detachment fails for a serial on another contract and keeps the unit', function (): void {
    [$owner, $other] = Contract::factory()->count(2)->create();
    $owner->devices()->attach(Device::factory()->create(), ['serial_number' => 'SN-0001']);

    expect(fn () => app(DetachDeviceFromContract::class)->handle($other, 'SN-0001'))
        ->toThrow(RecordsNotFoundException::class);

    $this->assertDatabaseHas('contract_device', ['serial_number' => 'SN-0001']);
});

test('device detachment fails for an unknown serial', function (): void {
    $contract = Contract::factory()->create();

    expect(fn () => app(DetachDeviceFromContract::class)->handle($contract, 'SN-UNKNOWN'))
        ->toThrow(RecordsNotFoundException::class);
});
