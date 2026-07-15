<?php

namespace Database\Factories;

use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $signedAt = Carbon::instance(fake()->dateTimeBetween('-2 years', '-6 months'));

        return [
            'uuid' => (string) Str::uuid7(),
            'contract_number' => mb_strtoupper(fake()->unique()->bothify('CTR-####-???')),
            'partner_name' => fake()->company(),
            'description' => fake()->optional()->paragraph(),
            'signed_at' => $signedAt,
            'starts_at' => $signedAt->copy()->addDays(14),
            'ends_at' => $signedAt->copy()->addYears(2),
        ];
    }

    /**
     * Indicate that the contract is currently active.
     */
    public function active(): static
    {
        return $this->state(function (array $attributes) {
            $signedAt = Carbon::now()->subMonths(3);

            return [
                'signed_at' => $signedAt,
                'starts_at' => $signedAt->copy()->addDays(14),
                'ends_at' => Carbon::now()->addYear(),
            ];
        });
    }

    /**
     * Indicate that the contract has already expired.
     */
    public function expired(): static
    {
        return $this->state(function (array $attributes) {
            $signedAt = Carbon::now()->subYears(3);

            return [
                'signed_at' => $signedAt,
                'starts_at' => $signedAt->copy()->addDays(14),
                'ends_at' => Carbon::now()->subMonths(6),
            ];
        });
    }

    /**
     * Indicate that the contract has not been signed yet.
     */
    public function unsigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'signed_at' => null,
            'starts_at' => null,
            'ends_at' => null,
        ]);
    }
}
