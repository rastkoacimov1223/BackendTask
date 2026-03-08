<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id'     => Order::factory(),
            'product_name' => fake()->words(fake()->numberBetween(1, 3), true),
            'price'        => fake()->randomFloat(2, 1, 500),
            'quantity'     => fake()->numberBetween(1, 10),
        ];
    }
}
