<?php

namespace App\Console\Commands;

use App\Models\SystemBackup;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class CreateSystemBackup extends Command
{
    protected $signature = 'system:backup-database';

    protected $description = 'Create an encrypted private database backup';

    public function handle(DatabaseBackupService $service): int
    {
        $backup = SystemBackup::create([
            'disk' => config('deployment.backup_disk'),
            'database_driver' => config('database.connections.'.config('database.default').'.driver'),
            'status' => 'queued',
        ]);
        $service->create($backup);
        $this->info('Backup '.$backup->id.' created and encrypted.');

        return self::SUCCESS;
    }
}
