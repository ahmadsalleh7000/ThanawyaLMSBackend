<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\CreateDatabaseBackup;
use App\Models\ManualPaymentSubmission;
use App\Models\SystemBackup;
use App\Services\AuditService;
use App\Services\DatabaseBackupService;
use App\Services\PlatformSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class AdminOperationsController extends Controller
{
    public function index(PlatformSettingsService $settings): View
    {
        try {
            $heartbeatValue = Cache::get('ops.queue_worker_heartbeat');
        } catch (Throwable) {
            $heartbeatValue = null;
        }
        $heartbeat = $heartbeatValue ? CarbonImmutable::parse($heartbeatValue) : null;

        return view('admin.system.operations', [
            'settings' => $settings->all(),
            'backups' => SystemBackup::with('requester:id,name')->latest()->limit(20)->get(),
            'failedJobs' => DB::table('failed_jobs')->latest('failed_at')->limit(30)->get(),
            'queue' => ['heartbeat' => $heartbeat, 'pending' => DB::table('jobs')->count(), 'oldest' => DB::table('jobs')->min('created_at')],
            'eligibleReceipts' => ManualPaymentSubmission::whereNull('receipt_deleted_at')->whereNotIn('status', ['submitted', 'under_review'])->where('created_at', '<', now()->subDays((int) $settings->get('payment_receipt_retention_days')))->count(),
        ]);
    }

    public function createBackup(Request $request, AuditService $audit): RedirectResponse
    {
        $backup = SystemBackup::create([
            'disk' => config('deployment.backup_disk'),
            'database_driver' => config('database.connections.'.config('database.default').'.driver'),
            'status' => 'queued',
            'requested_by' => $request->user()->id,
        ]);
        CreateDatabaseBackup::dispatch($backup->id);
        $audit->record($request, 'system_backup.requested', $backup);

        return back()->with('success', 'تمت إضافة النسخة الاحتياطية إلى قائمة المعالجة.');
    }

    public function verifyBackup(Request $request, SystemBackup $backup, DatabaseBackupService $service, AuditService $audit): RedirectResponse
    {
        $valid = $service->verify($backup);
        $audit->record($request, $valid ? 'system_backup.verified' : 'system_backup.verification_failed', $backup);

        return back()->with($valid ? 'success' : 'error', $valid ? 'تم فك التشفير والتحقق من البصمة بنجاح.' : 'فشل التحقق من النسخة الاحتياطية.');
    }

    public function retryJob(Request $request, string $uuid, AuditService $audit): RedirectResponse
    {
        abort_unless(DB::table('failed_jobs')->where('uuid', $uuid)->exists(), 404);
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        $audit->record($request, 'queue.failed_job_retried', $request->user());

        return back()->with('success', 'تمت إعادة المهمة الفاشلة إلى قائمة المعالجة.');
    }

    public function forgetJob(Request $request, string $uuid, AuditService $audit): RedirectResponse
    {
        abort_unless(DB::table('failed_jobs')->where('uuid', $uuid)->exists(), 404);
        Artisan::call('queue:forget', ['id' => $uuid]);
        $audit->record($request, 'queue.failed_job_forgotten', $request->user());

        return back()->with('success', 'تم حذف سجل المهمة الفاشلة.');
    }

    public function updatePolicies(Request $request, PlatformSettingsService $settings, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['payment_receipt_retention_days' => ['required', 'integer', 'min:30', 'max:3650'], 'database_backup_retention_days' => ['required', 'integer', 'min:7', 'max:3650'], 'queue_stale_minutes' => ['required', 'integer', 'min:2', 'max:1440']]);
        $settings->update($data, $request->user());
        $audit->record($request, 'operations.policies_updated', $request->user());

        return back()->with('success', 'تم تحديث سياسات التشغيل والاحتفاظ.');
    }
}
