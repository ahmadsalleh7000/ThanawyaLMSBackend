<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LoginEvent;
use App\Models\MediaLibraryItem;
use App\Models\ScheduledCourseAction;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PlatformSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AdminSystemController extends Controller
{
    public function index(PlatformSettingsService $settings): View
    {
        return view('admin.system.index', ['settings' => $settings->all(), 'health' => $this->healthChecks()]);
    }

    public function update(Request $request, PlatformSettingsService $settings, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'academy_name_ar' => ['required', 'string', 'max:255'], 'academy_name_en' => ['required', 'string', 'max:255'],
            'support_email' => ['required', 'email', 'max:255'], 'default_locale' => ['required', Rule::in(['ar', 'en'])],
            'registration_enabled' => ['sometimes', 'boolean'], 'maintenance_banner_enabled' => ['sometimes', 'boolean'],
            'maintenance_banner_ar' => ['nullable', 'string', 'max:1000'], 'maintenance_banner_en' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['registration_enabled'] = $request->boolean('registration_enabled');
        $data['maintenance_banner_enabled'] = $request->boolean('maintenance_banner_enabled');
        $settings->update($data, $request->user());
        $audit->record($request, 'platform_settings.updated', $request->user());

        return back()->with('success', 'تم تحديث إعدادات المنصة الآمنة.');
    }

    public function audit(Request $request): View
    {
        return view('admin.system.audit', ['logs' => $this->auditQuery($request)->paginate(40)->withQueryString(), 'staff' => User::where('role', User::ROLE_ADMIN)->orderBy('name')->get(['id', 'name'])]);
    }

    public function auditExport(Request $request): StreamedResponse
    {
        return response()->streamDownload(function () use ($request): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Administrator', 'Action', 'Resource type', 'Resource ID', 'IP address']);
            $this->auditQuery($request)->with('user:id,name')->chunk(500, function ($logs) use ($output): void {
                foreach ($logs as $log) {
                    fputcsv($output, [$log->created_at->toISOString(), $log->user?->name, $log->action, $log->auditable_type, $log->auditable_id, $log->ip_address]);
                }
            });
            fclose($output);
        }, 'audit-log-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function security(Request $request): View
    {
        $events = LoginEvent::query()->with('user:id,name')->when($request->filled('result'), fn ($query) => $query->where('successful', $request->string('result')->toString() === 'success'))->latest('occurred_at')->paginate(40)->withQueryString();
        $sessions = DB::table('sessions')->leftJoin('users', 'users.id', '=', 'sessions.user_id')->whereNotNull('sessions.user_id')->select(['sessions.id', 'sessions.user_id', 'sessions.ip_address', 'sessions.user_agent', 'sessions.last_activity', 'users.name', 'users.email'])->orderByDesc('sessions.last_activity')->limit(100)->get();

        return view('admin.system.security', compact('events', 'sessions'));
    }

    public function revokeSession(Request $request, string $session, AuditService $audit): RedirectResponse
    {
        abort_if(hash_equals($request->session()->getId(), $session), 409, 'Use logout to end your current session.');
        DB::table('sessions')->where('id', $session)->delete();
        $audit->record($request, 'security.session_revoked', $request->user());

        return back()->with('success', 'تم إنهاء الجلسة المحددة.');
    }

    private function auditQuery(Request $request)
    {
        return AuditLog::query()->with('user:id,name')->when($request->string('action')->trim()->toString(), fn ($query, $action) => $query->where('action', 'like', '%'.$action.'%'))->when($request->integer('user_id'), fn ($query, $userId) => $query->where('user_id', $userId))->when($request->date('from'), fn ($query, $date) => $query->where('created_at', '>=', $date->startOfDay()))->when($request->date('to'), fn ($query, $date) => $query->where('created_at', '<=', $date->endOfDay()))->latest();
    }

    private function healthChecks(): array
    {
        try {
            DB::select('select 1');
            $database = ['status' => 'healthy', 'detail' => 'الاتصال بقاعدة البيانات يعمل.'];
        } catch (Throwable $exception) {
            $database = ['status' => 'critical', 'detail' => mb_substr($exception->getMessage(), 0, 180)];
        }
        $storageWritable = is_writable(storage_path());
        $freeBytes = @disk_free_space(storage_path());

        return [
            'database' => $database,
            'storage' => ['status' => $storageWritable ? 'healthy' : 'critical', 'detail' => $storageWritable ? number_format(($freeBytes ?: 0) / 1024 / 1024 / 1024, 1).' GB مساحة متاحة' : 'مجلد التخزين غير قابل للكتابة.'],
            'queue' => ['status' => Schema::hasTable('failed_jobs') && DB::table('failed_jobs')->count() > 0 ? 'warning' : 'healthy', 'detail' => (Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0).' معلقة · '.(Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0).' فاشلة'],
            'media' => ['status' => MediaLibraryItem::where('processing_status', 'failed')->count() ? 'warning' : 'healthy', 'detail' => MediaLibraryItem::where('processing_status', 'failed')->count().' ملفات فاشلة'],
            'scheduler' => ['status' => ScheduledCourseAction::where('status', 'failed')->count() ? 'warning' : 'healthy', 'detail' => ScheduledCourseAction::where('status', 'pending')->where('run_at', '<', now())->count().' إجراءات متأخرة'],
        ];
    }
}
