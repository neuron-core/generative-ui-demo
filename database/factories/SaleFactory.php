<?php

namespace Database\Factories;

use App\Enums\SaleChannel;
use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Taking the most recent of two random dates skews volume towards today, simulating growth.
        $soldAt = max(
            fake()->dateTimeBetween('-18 months'),
            fake()->dateTimeBetween('-18 months'),
        );

        return [
            'user_id' => User::factory(),
            'status' => fake()->randomElement([
                ...array_fill(0, 17, SaleStatus::Completed),
                ...array_fill(0, 2, SaleStatus::Refunded),
                SaleStatus::Cancelled,
            ]),
            'channel' => fake()->randomElement([
                ...array_fill(0, 5, SaleChannel::Web),
                ...array_fill(0, 3, SaleChannel::Mobile),
                ...array_fill(0, 2, SaleChannel::Marketplace),
            ]),
            'total' => 0,
            'sold_at' => $soldAt,
            'created_at' => $soldAt,
            'updated_at' => $soldAt,
        ];
    }
}
