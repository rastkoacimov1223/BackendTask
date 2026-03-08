<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRevenueReportRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class UserRevenueReportController extends Controller
{
    /**
     * Return a paginated revenue summary per user for completed orders.
     *
     * Results can be filtered by an optional date range applied to
     * the order's `completed_at` timestamp.
     */
    public function __invoke(UserRevenueReportRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $startDate = isset($validated['start_date'])
            ? $request->date('start_date')->startOfDay()
            : null;

        $endDate = isset($validated['end_date'])
            ? $request->date('end_date')->endOfDay()
            : null;

        $perPage = (int) ($validated['per_page'] ?? 15);

        $completedOrders = fn (Builder $query) => $query
            ->completed()
            ->completedBetween($startDate, $endDate);

        $report = User::query()
            ->select(['users.id', 'users.email'])
            ->whereHas('orders', $completedOrders)
            ->withCount(['orders as orders_count' => $completedOrders])
            ->withSum(['orders as total_revenue' => $completedOrders], 'amount')
            ->orderBy('users.id')
            ->paginate($perPage)
            ->through(fn (User $user) => [
                'user_id' => $user->id,
                'email' => $user->email,
                'orders_count' => (int) $user->orders_count,
                'total_revenue' => round((float) ($user->total_revenue ?? 0), 2),
            ]);

        return response()->json($report);
    }
}
