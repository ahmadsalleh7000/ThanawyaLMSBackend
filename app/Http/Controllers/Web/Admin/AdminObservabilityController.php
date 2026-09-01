<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationIncident;
use App\Models\RequestMetric;
use App\Services\AuditService;
use App\Services\PlatformSettingsService;
use App\Services\ProductionReadinessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class AdminObservabilityController extends Controller
{
    public function index(PlatformSettingsService $settings, ProductionReadinessService $readiness): View
    {
        $metrics = RequestMetric::where('occurred_at', '>=', now()->subDay())->latest('occurred_at')->limit(10000)->get();
        $durations = $metrics->pluck('duration_ms')->sort()->values();
        $p95 = $durations->isEmpty() ? 0 : $durations[(int) floor(($durations->count() - 1) * .95)];
        $routes = $metrics->groupBy(fn ($metric) => $metric->route_name ?: 'unmatched')->map(fn ($items, $route) => ['route' => $route, 'requests' => $items->count(), 'average' => round($items->avg('duration_ms')), 'errors' => $items->where('status_code', '>=', 500)->count()])->sortByDesc('average')->take(10);

        return view('admin.system.observability', [
            'incidents' => ApplicationIncident::with('resolver:id,name')->latest('last_seen_at')->paginate(20),
            'summary' => ['requests' => $metrics->count(), 'average' => round($metrics->avg('duration_ms') ?? 0), 'p95' => $p95, 'errors' => $metrics->where('status_code', '>=', 500)->count()],
            'routes' => $routes, 'checks' => $readiness->checks(), 'settings' => $settings->all(),
        ]);
    }

    public function resolve(Request $request, ApplicationIncident $incident, AuditService $audit): RedirectResponse
    {
        abort_if($incident->resolved_at, 409, 'This incident is already resolved.');
        $data = $request->validate(['resolution_note' => ['required', 'string', 'min:5', 'max:3000']]);
        $before = $incident->attributesToArray();
        $incident->update(['resolved_at' => now(), 'resolved_by' => $request->user()->id, 'resolution_note' => $data['resolution_note']]);
        $audit->record($request, 'observability.incident_resolved', $incident, $before);

        return back()->with('success', 'تم إغلاق الحادث وتوثيق المعالجة.');
    }

    public function update(Request $request, PlatformSettingsService $settings, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['observability_slow_request_ms' => ['required', 'integer', 'min:100', 'max:60000'], 'observability_failed_login_threshold' => ['required', 'integer', 'min:3', 'max:1000'], 'observability_metrics_retention_days' => ['required', 'integer', 'min:7', 'max:365']]);
        $settings->update($data, $request->user());
        $audit->record($request, 'observability.settings_updated', $request->user());

        return back()->with('success', 'تم تحديث حدود المراقبة.');
    }

    public function run(Request $request, AuditService $audit): RedirectResponse
    {
        Artisan::call('ops:monitor-health');
        $audit->record($request, 'observability.monitor_run', $request->user());

        return back()->with('success', 'تم تشغيل فحص المراقبة الآن.');
    }
}
