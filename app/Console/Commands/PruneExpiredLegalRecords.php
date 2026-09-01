<?php

namespace App\Console\Commands;

use App\Models\AccountDeletionRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneExpiredLegalRecords extends Command
{
    protected $signature = 'privacy:prune-legal-records';

    protected $description = 'Delete retained commerce records after the configured legal retention period';

    public function handle(): int
    {
        $pruned = 0;
        AccountDeletionRequest::where('status', 'completed')->whereNull('legal_records_pruned_at')->where('legal_records_until', '<=', now())->orderBy('id')->each(function (AccountDeletionRequest $request) use (&$pruned): void {
            DB::transaction(function () use ($request): void {
                $orderIds = DB::table('orders')->where('user_id', $request->user_id)->pluck('id');
                DB::table('invoices')->whereIn('order_id', $orderIds)->delete();
                DB::table('orders')->whereIn('id', $orderIds)->delete();
                $request->update(['legal_records_pruned_at' => now()]);
            });
            $pruned++;
        });
        $this->info("Pruned legal records for {$pruned} anonymized accounts.");

        return self::SUCCESS;
    }
}
