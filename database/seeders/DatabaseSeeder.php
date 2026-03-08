<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the database with 10 users, each having a realistic mix of
     * completed, pending, and cancelled orders.
     */
    public function run(): void
    {
        User::factory(10)->create()->each(function (User $user): void {
            Order::factory()
                ->count(fake()->numberBetween(3, 8))
                ->for($user)
                ->completed()
                ->create([
                    'completed_at' => fake()->dateTimeBetween('-1 year', 'now'),
                ]);

            Order::factory()
                ->count(fake()->numberBetween(1, 3))
                ->for($user)
                ->create([
                    'status' => fake()->randomElement([
                        Order::STATUS_PENDING,
                        Order::STATUS_CANCELLED,
                    ]),
                    'completed_at' => null,
                ]);
        });
    }
}
