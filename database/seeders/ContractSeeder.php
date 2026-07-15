<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Device;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ContractSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed contracts and attach 1-5 device units to each.
     *
     * Devices are picked with replacement, so the same device type can appear
     * on one contract multiple times with different serial numbers.
     */
    public function run(): void
    {
        $devices = Device::all();

        Contract::factory()
            ->count(10)
            ->create()
            ->each(function (Contract $contract) use ($devices): void {
                foreach (range(1, random_int(1, 5)) as $ignored) {
                    $contract->devices()->attach($devices->random(), [
                        'serial_number' => mb_strtoupper(fake()->unique()->bothify('SN-########')),
                    ]);
                }
            });
    }
}
