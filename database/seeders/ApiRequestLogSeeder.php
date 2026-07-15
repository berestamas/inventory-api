<?php

namespace Database\Seeders;

use App\Models\ApiRequestLog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ApiRequestLogSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a realistic mix of successful and failed API request logs.
     */
    public function run(): void
    {
        ApiRequestLog::factory()->count(20)->create();
        ApiRequestLog::factory()->count(5)->failed()->create();
    }
}
