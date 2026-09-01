<?php

namespace App\Services;

use App\Models\SystemBackup;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    public function create(SystemBackup $backup): SystemBackup
    {
        $backup->update(['status' => 'running', 'error' => null]);
        try {
            $sql = $this->dump();
            $checksum = hash('sha256', $sql);
            $encrypted = Crypt::encryptString(gzencode($sql, 9));
            $path = 'backups/database-'.now()->format('Ymd-His').'-'.$backup->id.'.sql.gz.enc';
            throw_unless(Storage::disk($backup->disk)->put($path, $encrypted), RuntimeException::class, 'Could not write backup file.');
            $backup->update(['path' => $path, 'status' => 'completed', 'size_bytes' => Storage::disk($backup->disk)->size($path), 'content_sha256' => $checksum, 'completed_at' => now()]);

            return $backup->refresh();
        } catch (\Throwable $exception) {
            $backup->update(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 4000)]);
            throw $exception;
        }
    }

    public function verify(SystemBackup $backup): bool
    {
        if ($backup->status !== 'completed' || ! $backup->path || ! Storage::disk($backup->disk)->exists($backup->path)) {
            return false;
        }
        $compressed = Crypt::decryptString(Storage::disk($backup->disk)->get($backup->path));
        $sql = gzdecode($compressed);
        $valid = $sql !== false && hash_equals((string) $backup->content_sha256, hash('sha256', $sql));
        if ($valid) {
            $backup->update(['verified_at' => now()]);
        }

        return $valid;
    }

    private function dump(): string
    {
        $connection = config('database.default');
        $config = config('database.connections.'.$connection);
        if ($config['driver'] === 'sqlite') {
            $path = $config['database'];
            throw_if($path === ':memory:' || ! is_file($path), RuntimeException::class, 'SQLite database file is unavailable.');

            return file_get_contents($path);
        }
        throw_unless(in_array($config['driver'], ['mysql', 'mariadb'], true), RuntimeException::class, 'This database driver is not supported by the backup service.');
        $process = new Process([env('MYSQLDUMP_BINARY', 'mysqldump'), '--single-transaction', '--quick', '--skip-lock-tables', '--host='.$config['host'], '--port='.$config['port'], '--user='.$config['username'], $config['database']], null, ['MYSQL_PWD' => (string) $config['password']]);
        $process->setTimeout(300)->mustRun();

        return $process->getOutput();
    }
}
