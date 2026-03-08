<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcileOrders extends Command
{
    protected $signature = 'reconcile:orders
                            {--chunk=500 : Number of orders to process per database query}
                            {--threshold=0.01 : Maximum allowed difference before flagging a mismatch}';

    protected $description = 'Verify that each order\'s stored total_amount matches the sum of its order items (price × quantity).';

    public function handle(): int
    {
        $chunk     = (int) $this->option('chunk');
        $threshold = (float) $this->option('threshold');

        $this->info('Starting order reconciliation…');
        $this->newLine();

        $totalOrders  = DB::table('orders')->count();
        $mismatchCount = 0;

        if ($totalOrders === 0) {
            $this->warn('No orders found in the database.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($totalOrders);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — Elapsed: %elapsed:6s%');
        $bar->start();

        /*
         * Stream orders in fixed-size chunks joined with their pre-aggregated
         * item totals. Using a raw query avoids hydrating thousands of Eloquent
         * models and keeps memory usage constant regardless of dataset size.
         */
        DB::table('orders')
            ->orderBy('id')
            ->chunk($chunk, function ($orders) use ($threshold, &$mismatchCount, $bar): void {
                $orderIds = $orders->pluck('id');

                // Pre-aggregate order-item totals for this chunk in one query
                $calculatedTotals = DB::table('order_items')
                    ->whereIn('order_id', $orderIds)
                    ->select('order_id', DB::raw('ROUND(SUM(price * quantity), 2) as calculated_total'))
                    ->groupBy('order_id')
                    ->pluck('calculated_total', 'order_id');

                foreach ($orders as $order) {
                    $storedTotal     = (float) $order->total_amount;
                    $calculatedTotal = (float) ($calculatedTotals[$order->id] ?? 0.00);
                    $difference      = round($storedTotal - $calculatedTotal, 2);

                    if (abs($difference) > $threshold) {
                        $mismatchCount++;
                        $this->logMismatch($order->id, $storedTotal, $calculatedTotal, $difference);
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->printSummary($totalOrders, $mismatchCount);

        return self::SUCCESS;
    }

    /**
     * Write mismatch details to both the application log and the console.
     */
    private function logMismatch(int $orderId, float $stored, float $calculated, float $difference): void
    {
        $context = [
            'order_id'         => $orderId,
            'stored_total'     => $stored,
            'calculated_total' => $calculated,
            'difference'       => $difference,
        ];

        Log::warning('Order total mismatch detected.', $context);

        $this->line(sprintf(
            '  <fg=yellow>[MISMATCH]</> Order #%d | Stored: <fg=red>%.2f</> | Calculated: <fg=green>%.2f</> | Diff: <fg=yellow>%+.2f</>',
            $orderId,
            $stored,
            $calculated,
            $difference,
        ));
    }

    /**
     * Render the final reconciliation summary table.
     */
    private function printSummary(int $totalOrders, int $mismatchCount): void
    {
        $percentage = $totalOrders > 0
            ? round(($mismatchCount / $totalOrders) * 100, 2)
            : 0.0;

        $this->info('Reconciliation complete.');
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Orders Checked',  number_format($totalOrders)],
                ['Mismatched Orders',     number_format($mismatchCount)],
                ['Mismatch Percentage',   number_format($percentage, 2).'%'],
            ]
        );

        if ($mismatchCount === 0) {
            $this->info('All order totals are consistent.');
        } else {
            $this->warn(sprintf(
                '%s mismatched order(s) found. Details have been written to the application log.',
                number_format($mismatchCount)
            ));
        }
    }
}
