<?php

namespace App\Console\Commands;

use App\Services\ReleaseReadinessService;
use Illuminate\Console\Command;

class CheckReleaseReadiness extends Command
{
    protected $signature = 'ops:release-check';

    protected $description = 'Validate production configuration without exposing secret values';

    public function handle(ReleaseReadinessService $readiness): int
    {
        $checks = $readiness->checks();
        $this->table(
            ['Check', 'Status', 'Guidance'],
            collect($checks)->map(fn (array $check, string $name) => [
                $name,
                $check['ok'] ? 'PASS' : 'FAIL',
                $check['message'],
            ])->values()->all(),
        );

        if (! $readiness->ready()) {
            $this->error('Release blocked: resolve every failed production check.');

            return self::FAILURE;
        }

        $this->info('Production configuration is ready for deployment.');

        return self::SUCCESS;
    }
}
