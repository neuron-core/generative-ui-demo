<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Demo login: test@example.com / password
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Customers, registered progressively over the last two years.
        User::factory(250)
            ->state(fn (): array => ['created_at' => fake()->dateTimeBetween('-2 years', '-18 months')])
            ->create();

        $this->call([
            CatalogSeeder::class,
            SaleSeeder::class,
        ]);
    }
}
