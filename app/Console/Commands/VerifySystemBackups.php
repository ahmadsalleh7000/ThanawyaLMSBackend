<?php

namespace App\Console\Commands;

use App\Models\SystemBackup;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class VerifySystemBackups extends Command
{
    protected $signature = 'system:verify-backups {backup?}';

    protected $description = 'Decrypt and verify stored database backup checksums';

    public function handle(DatabaseBackupService $service): int
    {
        $backups = $this->argument('backup') ? SystemBackup::whereKey($this->argument('backup'))->get() : SystemBackup::where('status', 'completed')->get();
        $failed = $backups->filter(fn ($backup) => ! $service->verify($backup));
        $this->info(($backups->count() - $failed->count()).' verified; '.$failed->count().' failed.');

        return $failed->isEmpty() ? self::SUCCESS : self::FAILURE;
    }
}
