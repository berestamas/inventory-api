<?php

use App\Models\Contract;
use App\Models\ContractDevice;
use App\Models\Device;
use App\Models\User;

test('database seeder runs', function (): void {
    $this->seed();

    expect(User::query()->count())->toBeGreaterThan(0)
        ->and(Device::query()->count())->toBeGreaterThan(0)
        ->and(Contract::query()->count())->toBeGreaterThan(0)
        ->and(ContractDevice::query()->count())->toBeGreaterThan(0);
});

test('database seeder is idempotent for fixed records', function (): void {
    $this->seed();
    $this->seed();

    expect(User::query()->where('email', 'test@example.com')->count())->toBe(1);
});
