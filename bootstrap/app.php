<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureAdminPermission;
use App\Http\Middleware\EnsurePasswordHasBeenChanged;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\RecordRequestMetrics;
use App\Services\IncidentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', '')),
        )));
        $middleware->trustProxies(
            at: $trustedProxies,
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
        );
        $middleware->append(AddSecurityHeaders::class);
        $middleware->append(RecordRequestMetrics::class);
        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'admin.permission' => EnsureAdminPermission::class,
            'password.changed' => EnsurePasswordHasBeenChanged::class,
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $arabic = str_starts_with((string) $request->header('Accept-Language', 'en'), 'ar');

            return response()->json([
                'message' => $arabic
                    ? 'تم إرسال عدة محاولات خلال وقت قصير. انتظر قليلاً ثم حاول مرة أخرى.'
                    : 'Too many attempts in a short time. Please wait a moment and try again.',
            ], 429, array_filter([
                'Retry-After' => $exception->getHeaders()['Retry-After'] ?? null,
                'X-RateLimit-Reset' => $exception->getHeaders()['X-RateLimit-Reset'] ?? null,
            ]));
        });
        $exceptions->render(function (PostTooLargeException $exception, Request $request) {
            $limit = ini_get('post_max_size') ?: 'the configured server limit';

            if ($request->is('api/*')) {
                return response()->json([
                    'message' => "The uploaded file is larger than the server request limit ({$limit}).",
                ], 413);
            }

            if ($request->is('admin/*')) {
                return redirect()->back()->with('error', "حجم الملف أكبر من حد الخادم الحالي ({$limit}). شغّل الخادم المحلي عبر composer run serve أو php -c php.ini artisan serve ثم أعد المحاولة.");
            }

            return response('The uploaded file is larger than the server request limit.', 413);
        });
        $exceptions->render(function (QueryException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'The service could not save your changes. Please try again.',
                ], 500);
            }

            if ($request->is('admin/*')) {
                return redirect()->back()->with('error', 'تعذر حفظ التغييرات بأمان. لم يتم اعتماد العملية، ويمكنك مراجعة بيانات الحساب والمحاولة مجدداً.');
            }

            return null;
        });
        $exceptions->report(function (Throwable $exception): void {
            Log::error('application.exception', ['class' => $exception::class, 'message' => $exception->getMessage(), 'file' => $exception->getFile(), 'line' => $exception->getLine()]);
            try {
                if (Schema::hasTable('application_incidents')) {
                    app(IncidentService::class)->record('exception', 'critical', class_basename($exception), $exception->getMessage(), ['file' => $exception->getFile(), 'line' => $exception->getLine()], hash('sha256', $exception::class.'|'.$exception->getFile().'|'.$exception->getLine()));
                }
            } catch (Throwable) {
                // Reporting must never mask the original exception.
            }
        });
    })->create();
