<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CheckDeploymentReadiness extends Command
{
    protected $signature = 'deploy:check';

    protected $description = 'Validate production-critical configuration before deployment';

    public function handle(): int
    {
        $checks = [
            'Application environment is production' => app()->isProduction(),
            'Debug mode is disabled' => ! config('app.debug'),
            'Application key is configured' => filled(config('app.key')),
            'Application URL uses HTTPS' => Str::startsWith((string) config('app.url'), 'https://'),
            'Frontend URL uses HTTPS' => Str::startsWith((string) config('app.frontend_url'), 'https://'),
            'Queue is asynchronous' => config('queue.default') !== 'sync',
            'Session cookies are secure' => (bool) config('session.secure'),
            'Session data is encrypted' => (bool) config('session.encrypt'),
            'Structured production logging is enabled' => config('logging.default') === 'structured',
            'Mail transport is not the log driver' => config('mail.default') !== 'log',
            'No insecure bootstrap admin password is configured' => ! in_array((string) env('ADMIN_PASSWORD'), ['password', 'password123'], true),
        ];

        foreach ($checks as $label => $passed) {
            if ($passed) {
                $this->components->info($label);
            } else {
                $this->components->error($label);
            }
        }

        if (in_array(false, $checks, true)) {
            $this->newLine();
            $this->error('Deployment blocked: resolve every failed production check.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Production configuration passed all deployment checks.');

        return self::SUCCESS;
    }
}
