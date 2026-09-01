<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentReconciliationPeriod;
use App\Models\ReconciliationAlert;
use App\Services\AuditService;
use App\Services\PaymentReconciliationService;
use App\Services\PlatformSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReconciliationController extends Controller
{
    public function index(PaymentReconciliationService $service, PlatformSettingsService $settings): View
    {
        $periods = PaymentReconciliationPeriod::with(['lines', 'creator:id,name', 'closer:id,name'])->latest('starts_on')->paginate(12);
        $periods->getCollection()->where('status', 'open')->each(fn ($period) => $service->refresh($period));

        return view('admin.reconciliation.index', [
            'periods' => $periods,
            'alerts' => ReconciliationAlert::whereNull('resolved_at')->with('period:id,name')->latest('detected_at')->limit(10)->get(),
            'monitoringSettings' => $settings->all(),
            'trend' => PaymentReconciliationPeriod::where('status', 'closed')->with('lines')->latest('ends_on')->limit(8)->get()->reverse()->values(),
        ]);
    }

    public function store(Request $request, PaymentReconciliationService $service, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on']]);
        $period = $service->create($data, $request->user());
        $audit->record($request, 'reconciliation.created', $period);

        return redirect()->route('admin.reconciliation.show', $period)->with('success', 'تم إنشاء فترة المطابقة.');
    }

    public function show(PaymentReconciliationPeriod $period, PaymentReconciliationService $service): View
    {
        $service->refresh($period);

        return view('admin.reconciliation.show', ['period' => $period->load(['lines', 'creator:id,name', 'closer:id,name'])]);
    }

    public function update(Request $request, PaymentReconciliationPeriod $period, AuditService $audit): RedirectResponse
    {
        abort_unless($period->status === 'open', 409, 'Closed periods are immutable.');
        $channels = array_keys(config('payments.manual_transfer.channels'));
        $data = $request->validate([
            'lines' => ['required', 'array'], 'lines.*.channel' => ['required', Rule::in($channels)],
            'lines.*.actual_inflow' => ['required', 'numeric', 'min:0'], 'lines.*.actual_refunds' => ['required', 'numeric', 'min:0'],
            'lines.*.note' => ['nullable', 'string', 'max:1000'],
        ]);
        $before = $period->load('lines')->toArray();
        foreach ($data['lines'] as $line) {
            $period->lines()->where('channel', $line['channel'])->update(['actual_inflow' => $line['actual_inflow'], 'actual_refunds' => $line['actual_refunds'], 'note' => $line['note'] ?? null]);
        }
        $audit->record($request, 'reconciliation.statement_totals_updated', $period, $before);

        return back()->with('success', 'تم حفظ إجماليات كشف الحساب.');
    }

    public function close(Request $request, PaymentReconciliationPeriod $period, PaymentReconciliationService $service, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['close_note' => ['nullable', 'string', 'max:2000']]);
        $before = $period->attributesToArray();
        $closed = $service->close($period, $request->user(), $data['close_note'] ?? null);
        $audit->record($request, 'reconciliation.closed', $closed, $before);

        return back()->with('success', 'تم إغلاق الفترة وتجميد أرقام المطابقة.');
    }

    public function export(PaymentReconciliationPeriod $period): StreamedResponse
    {
        $period->load('lines');

        return response()->streamDownload(function () use ($period): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Period', $period->name, 'From', $period->starts_on->toDateString(), 'To', $period->ends_on->toDateString(), 'Status', $period->status]);
            fputcsv($output, ['Channel', 'Approved count', 'Expected inflow', 'Refund count', 'Expected refunds', 'Statement inflow', 'Statement refunds', 'Net variance', 'Note']);
            foreach ($period->lines as $line) {
                fputcsv($output, [$line->channel, $line->approved_count, $line->expected_inflow, $line->refund_count, $line->expected_refunds, $line->actual_inflow, $line->actual_refunds, $line->variance, $line->note]);
            }
            fclose($output);
        }, 'reconciliation-'.$period->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function updateMonitoring(Request $request, PlatformSettingsService $settings, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'reconciliation_variance_threshold' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'reconciliation_overdue_days' => ['required', 'integer', 'min:0', 'max:30'],
            'reconciliation_daily_summary_enabled' => ['sometimes', 'boolean'],
        ]);
        $data['reconciliation_daily_summary_enabled'] = $request->boolean('reconciliation_daily_summary_enabled');
        $settings->update($data, $request->user());
        $audit->record($request, 'reconciliation.monitoring_settings_updated', $request->user());

        return back()->with('success', 'تم تحديث قواعد التنبيه المالي.');
    }

    public function resolveAlert(Request $request, ReconciliationAlert $alert, AuditService $audit): RedirectResponse
    {
        abort_if($alert->resolved_at, 409, 'This alert is already resolved.');
        $data = $request->validate(['resolution_note' => ['required', 'string', 'min:5', 'max:2000']]);
        $before = $alert->attributesToArray();
        $alert->update(['resolved_at' => now(), 'resolved_by' => $request->user()->id, 'resolution_note' => $data['resolution_note']]);
        $audit->record($request, 'reconciliation.alert_resolved', $alert, $before);

        return back()->with('success', 'تم إغلاق التنبيه مع حفظ سبب المعالجة.');
    }
}
