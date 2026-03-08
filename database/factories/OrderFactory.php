<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => fake()->randomElement([
                Order::STATUS_PENDING,
                Order::STATUS_COMPLETED,
                Order::STATUS_CANCELLED,
            ]),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'completed_at' => fake()->optional()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    /**
     * Mark the order as completed with an optional explicit timestamp.
     */
    public function completed(?string $completedAt = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_COMPLETED,
            'completed_at' => $completedAt ?? now(),
        ]);
    }
}
