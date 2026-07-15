<?php

namespace Database\Factories;

use App\Models\ApiRequestLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiRequestLog>
 */
class ApiRequestLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'method' => fake()->randomElement(['GET', 'POST', 'PATCH', 'DELETE']),
            'path' => 'api/v1/'.fake()->randomElement(['devices', 'contracts']),
            'query' => null,
            'request_headers' => ['accept' => ['application/json']],
            'request_body' => null,
            'status' => fake()->randomElement([200, 201, 204]),
            'response_body' => json_encode(['data' => []]),
            'duration_ms' => fake()->numberBetween(2, 250),
            'ip' => fake()->ipv4(),
            'created_at' => fake()->dateTimeBetween('-14 days'),
        ];
    }

    /**
     * Indicate that the logged request failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => fake()->randomElement([404, 422, 500]),
            'response_body' => json_encode(['message' => 'Resource not found.']),
        ]);
    }
}
