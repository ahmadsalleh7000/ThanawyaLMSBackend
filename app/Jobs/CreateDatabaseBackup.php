<?php

namespace App\Jobs;

use App\Models\SystemBackup;
use App\Services\DatabaseBackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateDatabaseBackup implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 360;

    public function __construct(public readonly int $backupId) {}

    public function handle(DatabaseBackupService $service): void
    {
        $service->create(SystemBackup::findOrFail($this->backupId));
    }
}
