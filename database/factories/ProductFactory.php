<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 9, 900);

        return [
            'category_id' => Category::factory(),
            'name' => ucwords(fake()->unique()->words(3, true)),
            'sku' => strtoupper(fake()->unique()->bothify('???-#####')),
            'price' => $price,
            'cost' => round($price * fake()->randomFloat(2, 0.35, 0.75), 2),
            'stock' => fake()->numberBetween(0, 500),
            'is_active' => fake()->boolean(90),
        ];
    }

    /**
     * Indicate that the product is out of stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }
}
