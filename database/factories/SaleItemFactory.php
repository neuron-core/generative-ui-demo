<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->randomElement([1, 1, 1, 1, 2, 2, 3, 4]),
            'unit_price' => fake()->randomFloat(2, 9, 900),
        ];
    }
}
