<?php

namespace App\Http\Middleware;

use App\Models\RequestMetric;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordRequestMetrics
{
    private static ?bool $tableAvailable = null;

    public function handle(Request $request, Closure $next): Response
    {
        $started = hrtime(true);
        $response = $next($request);
        if (! $request->is('api/*') && ! $request->is('admin*')) {
            return $response;
        }
        $duration = (int) round((hrtime(true) - $started) / 1_000_000);
        $context = ['route' => $request->route()?->getName(), 'method' => $request->method(), 'status' => $response->getStatusCode(), 'duration_ms' => $duration, 'user_id' => $request->user()?->id];
        Log::info('http.request', $context);
        try {
            self::$tableAvailable ??= Schema::hasTable('request_metrics');
            if (self::$tableAvailable) {
                RequestMetric::create(['user_id' => $request->user()?->id, 'route_name' => $context['route'], 'method' => $context['method'], 'status_code' => $context['status'], 'duration_ms' => $duration, 'memory_kb' => (int) round(memory_get_peak_usage(true) / 1024), 'occurred_at' => now()]);
            }
        } catch (Throwable $exception) {
            Log::warning('observability.metric_failed', ['message' => $exception->getMessage()]);
        }

        return $response;
    }
}
