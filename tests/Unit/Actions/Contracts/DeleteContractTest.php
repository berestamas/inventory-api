<?php

use App\Actions\Contracts\DeleteContract;
use App\Models\Contract;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('contract deletion removes the contract and its units but keeps devices', function (): void {
    $contract = Contract::factory()->create();
    $device = Device::factory()->create();
    $contract->devices()->attach($device, ['serial_number' => 'SN-00000001']);

    app(DeleteContract::class)->handle($contract);

    $this->assertDatabaseMissing('contracts', ['id' => $contract->id]);
    $this->assertDatabaseMissing('contract_device', ['serial_number' => 'SN-00000001']);
    $this->assertDatabaseHas('devices', ['id' => $device->id]);
});
