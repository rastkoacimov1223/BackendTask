<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRevenueReportTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/reports/user-revenue';

    /**
     * Only completed orders should appear in the revenue totals;
     * pending and cancelled orders must be excluded.
     */
    public function test_it_returns_completed_order_totals_per_user(): void
    {
        $firstUser = User::factory()->create(['email' => 'first@example.com']);
        $secondUser = User::factory()->create(['email' => 'second@example.com']);

        Order::factory()->for($firstUser)->completed('2024-01-10 10:00:00')->create(['amount' => 100]);
        Order::factory()->for($firstUser)->completed('2024-01-12 10:00:00')->create(['amount' => 49.50]);
        Order::factory()->for($firstUser)->create(['status' => Order::STATUS_PENDING,   'amount' => 999,  'completed_at' => null]);

        Order::factory()->for($secondUser)->completed('2024-01-15 10:00:00')->create(['amount' => 25]);
        Order::factory()->for($secondUser)->create(['status' => Order::STATUS_CANCELLED, 'amount' => 500, 'completed_at' => null]);

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.0.user_id', $firstUser->id)
            ->assertJsonPath('data.0.email', 'first@example.com')
            ->assertJsonPath('data.0.orders_count', 2)
            ->assertJsonPath('data.0.total_revenue', 149.5)
            ->assertJsonPath('data.1.user_id', $secondUser->id)
            ->assertJsonPath('data.1.orders_count', 1)
            ->assertJsonPath('data.1.total_revenue', 25);
    }

    /**
     * When start_date / end_date are supplied, only orders whose
     * `completed_at` falls within that range should be counted.
     */
    public function test_it_filters_results_by_completed_at_date_range(): void
    {
        $includedUser = User::factory()->create(['email' => 'included@example.com']);
        $excludedUser = User::factory()->create(['email' => 'excluded@example.com']);

        Order::factory()->for($includedUser)->completed('2024-01-10 08:00:00')->create(['amount' => 75]);
        Order::factory()->for($includedUser)->completed('2024-01-20 08:00:00')->create(['amount' => 25]);
        Order::factory()->for($excludedUser)->completed('2024-02-02 08:00:00')->create(['amount' => 150]);

        $this->getJson(self::ENDPOINT.'?start_date=2024-01-01&end_date=2024-01-31')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $includedUser->id)
            ->assertJsonPath('data.0.orders_count', 2)
            ->assertJsonPath('data.0.total_revenue', 100);
    }

    /**
     * The endpoint must paginate results and respect `per_page` / `page` params.
     */
    public function test_it_paginates_results(): void
    {
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            Order::factory()->for($user)->completed()->create(['amount' => 10]);
        }

        $this->getJson(self::ENDPOINT.'?per_page=2&page=2')
            ->assertOk()
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('per_page', 2)
            ->assertJsonCount(1, 'data');
    }

    /**
     * Invalid date range (start after end) and a zero page number
     * must each produce a validation error.
     */
    public function test_it_validates_the_date_range(): void
    {
        $this->getJson(self::ENDPOINT.'?start_date=2024-02-01&end_date=2024-01-01&page=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_date', 'end_date', 'page']);
    }
}
