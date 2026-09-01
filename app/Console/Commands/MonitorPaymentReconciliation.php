<?php

namespace App\Console\Commands;

use App\Services\ReconciliationMonitoringService;
use Illuminate\Console\Command;

class MonitorPaymentReconciliation extends Command
{
    protected $signature = 'payments:monitor-reconciliation {--summary : Send the daily finance summary}';

    protected $description = 'Detect reconciliation variances and overdue closing periods';

    public function handle(ReconciliationMonitoringService $monitoring): int
    {
        $result = $monitoring->run();
        $sent = $this->option('summary') ? $monitoring->sendDailySummary() : 0;
        $this->info("Created {$result['created']} alerts; {$result['open']} remain open; sent {$sent} summaries.");

        return self::SUCCESS;
    }
}
