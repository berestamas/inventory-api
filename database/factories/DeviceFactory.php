<?php

namespace Database\Factories;

use App\Enums\DeviceCategory;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'name' => fake()->words(nb: 2, asText: true),
            'manufacturer' => fake()->randomElement(['Dell', 'HP', 'Lenovo', 'Apple', 'Samsung', 'Cisco', 'Brother']),
            'category' => fake()->randomElement(DeviceCategory::cases()),
            'description' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the device belongs to the given category.
     */
    public function ofCategory(DeviceCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
        ]);
    }
}
