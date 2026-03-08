<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Total users to create. Each user gets a realistic mix of orders.
     * At ~10 orders per user on average, 1 000 users ≈ 10 000 orders total.
     */
    private const USER_COUNT = 1_000;

    /**
     * Permille (‰) chance any single order gets a deliberately wrong
     * total_amount to simulate real-world reconciliation failures.
     * 3 out of 1000 ≈ 0.30 % — close to the 0.27 % shown in the task spec.
     */
    private const MISMATCH_PERMILLE = 3;

    public function run(): void
    {
        $this->command->info('Seeding users…');
        $users = User::factory(self::USER_COUNT)->create();

        $this->command->info('Seeding orders and order items…');
        $bar = $this->command->getOutput()->createProgressBar(self::USER_COUNT);
        $bar->start();

        $mismatchCount = 0;

        $users->each(function (User $user) use ($bar, &$mismatchCount): void {
            // 3–8 completed orders
            $completedOrders = Order::factory()
                ->count(fake()->numberBetween(3, 8))
                ->for($user)
                ->completed()
                ->create([
                    'amount'       => 0,
                    'total_amount' => 0,
                    'completed_at' => fake()->dateTimeBetween('-1 year', 'now'),
                ]);

            // 1–3 pending / cancelled orders
            $otherOrders = Order::factory()
                ->count(fake()->numberBetween(1, 3))
                ->for($user)
                ->create([
                    'status'       => fake()->randomElement([
                        Order::STATUS_PENDING,
                        Order::STATUS_CANCELLED,
                    ]),
                    'amount'       => 0,
                    'total_amount' => 0,
                    'completed_at' => null,
                ]);

            $allOrders = $completedOrders->merge($otherOrders);

            foreach ($allOrders as $order) {
                $items = $this->createItemsForOrder($order);
                $calculatedTotal = $this->sumItems($items);

                // Deliberately corrupt a small percentage of totals (~0.3 %)
                $isMismatch = fake()->numberBetween(1, 1000) <= self::MISMATCH_PERMILLE;
                if ($isMismatch) {
                    $mismatchCount++;
                    $corruptedTotal = round(
                        $calculatedTotal + fake()->randomFloat(2, 1, 200) * fake()->randomElement([-1, 1]),
                        2
                    );
                    $order->update([
                        'amount'       => $corruptedTotal,
                        'total_amount' => $corruptedTotal,
                    ]);
                } else {
                    $order->update([
                        'amount'       => $calculatedTotal,
                        'total_amount' => $calculatedTotal,
                    ]);
                }
            }

            $bar->advance();
        });

        $bar->finish();

        $totalOrders = Order::count();
        $this->command->newLine(2);
        $this->command->info(sprintf(
            'Done. %s orders created (%s mismatches injected, %.2f%%).',
            number_format($totalOrders),
            number_format($mismatchCount),
            $totalOrders > 0 ? ($mismatchCount / $totalOrders) * 100 : 0,
        ));
    }

    /**
     * Insert 1–5 order items for the given order and return them.
     *
     * @return Collection<int, OrderItem>
     */
    private function createItemsForOrder(Order $order): Collection
    {
        return OrderItem::factory()
            ->count(fake()->numberBetween(1, 5))
            ->for($order)
            ->create();
    }

    /**
     * Calculate sum(price × quantity) for a collection of OrderItem instances.
     */
    private function sumItems(Collection $items): float
    {
        return round(
            $items->sum(fn (OrderItem $item) => (float) $item->price * $item->quantity),
            2
        );
    }
}
