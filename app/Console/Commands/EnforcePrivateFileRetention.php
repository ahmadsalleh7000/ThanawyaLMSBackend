<?php

namespace App\Console\Commands;

use App\Models\ManualPaymentSubmission;
use App\Models\SystemBackup;
use App\Services\PlatformSettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class EnforcePrivateFileRetention extends Command
{
    protected $signature = 'system:enforce-retention';

    protected $description = 'Delete expired private receipts and prune backup files according to policy';

    public function handle(PlatformSettingsService $settings): int
    {
        $receiptDays = (int) $settings->get('payment_receipt_retention_days');
        $receipts = 0;
        ManualPaymentSubmission::whereNull('receipt_deleted_at')->whereNotIn('status', ['submitted', 'under_review'])->where('created_at', '<', now()->subDays($receiptDays))->each(function ($submission) use (&$receipts) {
            Storage::disk($submission->receipt_disk)->delete($submission->receipt_path);
            $submission->update(['receipt_deleted_at' => now()]);
            $receipts++;
        });
        $backupDays = (int) $settings->get('database_backup_retention_days');
        $backups = 0;
        SystemBackup::where('status', 'completed')->where('created_at', '<', now()->subDays($backupDays))->each(function ($backup) use (&$backups) {
            Storage::disk($backup->disk)->delete($backup->path);
            $backup->update(['status' => 'pruned', 'path' => null]);
            $backups++;
        });
        $this->info("Deleted {$receipts} receipts and pruned {$backups} backups.");

        return self::SUCCESS;
    }
}
