<?php

namespace App\Console\Commands;

use App\Billing\Billing;
use Illuminate\Console\Command;

/**
 * Daily billing lifecycle (scheduled in routes/console.php): ends trials,
 * issues renewal invoices, marks unpaid periods past due, and moves
 * companies to the free plan after the grace period.
 */
class RunBilling extends Command
{
    protected $signature = 'billing:run';

    protected $description = 'Issue renewal invoices and apply trial / non-payment rules';

    public function handle(Billing $billing): int
    {
        $done = $billing->runDaily();

        $this->info(collect($done)->map(fn ($count, $step) => str_replace('_', ' ', $step).": {$count}")->implode(', '));

        return self::SUCCESS;
    }
}
