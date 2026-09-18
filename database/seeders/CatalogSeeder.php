<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /**
     * Product names per category, with the price range used for that category.
     *
     * @var array<string, array{range: array{int, int}, products: list<string>}>
     */
    protected const CATALOG = [
        'Electronics' => [
            'range' => [49, 1299],
            'products' => ['Wireless Headphones', 'Bluetooth Speaker', '4K Monitor', 'Mechanical Keyboard', 'Smartwatch', 'Action Camera', 'USB-C Hub', 'Noise Cancelling Earbuds'],
        ],
        'Home & Kitchen' => [
            'range' => [15, 399],
            'products' => ['Espresso Machine', 'Air Fryer', 'Chef Knife Set', 'Cast Iron Skillet', 'Robot Vacuum', 'Blender Pro', 'Ceramic Dinner Set', 'Electric Kettle'],
        ],
        'Sports & Outdoors' => [
            'range' => [12, 599],
            'products' => ['Yoga Mat', 'Trail Running Shoes', 'Camping Tent', 'Adjustable Dumbbells', 'Insulated Water Bottle', 'Hiking Backpack', 'Cycling Helmet', 'Resistance Bands'],
        ],
        'Fashion' => [
            'range' => [19, 349],
            'products' => ['Leather Jacket', 'Denim Jeans', 'Cotton T-Shirt', 'Wool Sweater', 'Canvas Sneakers', 'Leather Belt', 'Aviator Sunglasses', 'Rain Coat'],
        ],
        'Beauty' => [
            'range' => [8, 129],
            'products' => ['Vitamin C Serum', 'Hydrating Face Cream', 'Hair Dryer', 'Beard Trimmer', 'Eau de Parfum', 'Sunscreen SPF 50', 'Makeup Brush Set'],
        ],
        'Books' => [
            'range' => [9, 59],
            'products' => ['The Pragmatic Developer', 'Cooking for Two', 'A History of Tomorrow', 'Mindful Habits', 'Sci-Fi Anthology', 'Learn to Draw'],
        ],
        'Toys & Games' => [
            'range' => [10, 199],
            'products' => ['Building Blocks Set', 'Strategy Board Game', 'RC Racing Car', 'Puzzle 1000 Pieces', 'Plush Bear', 'Wooden Train Set', 'Science Kit'],
        ],
        'Office' => [
            'range' => [6, 499],
            'products' => ['Ergonomic Chair', 'Standing Desk', 'Notebook Pack', 'Gel Pen Set', 'Desk Lamp', 'Monitor Arm', 'Whiteboard', 'Laptop Stand'],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::CATALOG as $categoryName => $definition) {
            $category = Category::factory()->create(['name' => $categoryName]);

            foreach ($definition['products'] as $productName) {
                $price = fake()->randomFloat(2, ...$definition['range']);

                Product::factory()->for($category)->create([
                    'name' => $productName,
                    'price' => $price,
                    'cost' => round($price * fake()->randomFloat(2, 0.35, 0.75), 2),
                ]);
            }
        }

        // A few products with no stock left, to make inventory questions interesting.
        Product::query()->inRandomOrder()->limit(5)->update(['stock' => 0]);
    }
}
