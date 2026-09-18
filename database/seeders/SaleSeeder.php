<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class SaleSeeder extends Seeder
{
    protected const SALES_COUNT = 3000;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userIds = User::query()->pluck('id');
        $products = Product::query()->get(['id', 'price']);
        $now = now();

        $sales = [];
        $items = [];

        // Rows are built from the factories but inserted in bulk to keep the reload fast.
        for ($saleId = 1; $saleId <= self::SALES_COUNT; $saleId++) {
            $total = 0;

            foreach ($products->random(fake()->numberBetween(1, 4)) as $product) {
                $item = SaleItem::factory()->raw([
                    'sale_id' => $saleId,
                    'product_id' => $product->id,
                    'unit_price' => $product->price,
                ]);

                $total += $item['quantity'] * $item['unit_price'];
                $items[] = [...$item, 'created_at' => $now, 'updated_at' => $now];
            }

            $sales[] = [
                ...Sale::factory()->raw(['user_id' => $userIds->random()]),
                'id' => $saleId,
                'total' => $total,
            ];
        }

        foreach (array_chunk($sales, 500) as $chunk) {
            Sale::query()->insert($chunk);
        }

        foreach (array_chunk($items, 500) as $chunk) {
            SaleItem::query()->insert($chunk);
        }
    }
}
