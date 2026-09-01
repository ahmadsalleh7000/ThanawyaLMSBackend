<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductionReadinessService
{
    public function checks(): array
    {
        $checks = [];
        try {
            DB::select('select 1');
            $checks['database'] = ['ok' => true, 'message' => 'Database connection is healthy.'];
        } catch (Throwable) {
            $checks['database'] = ['ok' => false, 'message' => 'Database connection failed.'];
        }
        try {
            $probe = 'health/probe-'.uniqid().'.txt';
            $ok = Storage::disk('local')->put($probe, 'ok') && Storage::disk('local')->delete($probe);
            $checks['storage'] = ['ok' => $ok, 'message' => $ok ? 'Private storage is writable.' : 'Private storage is not writable.'];
        } catch (Throwable) {
            $checks['storage'] = ['ok' => false, 'message' => 'Private storage check failed.'];
        }
        try {
            $scheduler = Cache::get('ops.scheduler_heartbeat');
            $checks['scheduler'] = ['ok' => $scheduler && now()->diffInMinutes($scheduler) <= 3, 'message' => $scheduler ? 'Last heartbeat '.$scheduler : 'No scheduler heartbeat recorded.'];
            $queue = Cache::get('ops.queue_worker_heartbeat');
            $pending = DB::table('jobs')->count();
            $checks['queue'] = ['ok' => $pending === 0 || ($queue && now()->diffInMinutes($queue) <= 15), 'message' => $queue ? "{$pending} pending; last worker heartbeat {$queue}." : "{$pending} pending; no worker heartbeat."];
        } catch (Throwable) {
            $checks['runtime'] = ['ok' => false, 'message' => 'Runtime health cache is unavailable.'];
        }
        $checks['configuration'] = ['ok' => ! config('app.debug') && filled(config('app.key')) && config('queue.default') !== 'sync', 'message' => config('app.debug') ? 'APP_DEBUG must be false in production.' : 'Core production configuration is present.'];

        return $checks;
    }

    public function ready(): bool
    {
        return collect($this->checks())->every('ok');
    }
}
