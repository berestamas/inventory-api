<?php

namespace Database\Seeders;

use App\Models\Device;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeviceSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a realistic pool of device types.
     */
    public function run(): void
    {
        Device::factory()->count(20)->create();
    }
}
