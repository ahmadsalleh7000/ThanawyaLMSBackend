<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\ApplicationIncident;
use App\Models\ContactMessage;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ManualPaymentSubmission;
use App\Models\Order;
use App\Models\ReconciliationAlert;
use App\Models\StudentSupportCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminDashboardService
{
    public function for(User $user): array
    {
        return [
            'roleLabel' => config("admin_roles.roles.{$user->adminRole()}.label", 'الإدارة'),
            'stats' => $this->stats($user),
            'tasks' => $this->tasks($user),
            'quickActions' => $this->quickActions($user),
        ];
    }

    private function stats(User $user): array
    {
        $stats = [];

        if ($user->hasAdminPermission('users.manage')) {
            $stats[] = $this->stat('students', User::students()->count(), 'الطلاب', 'حساب طالب مسجل', 'blue');
            $stats[] = $this->stat('parents', User::parents()->count(), 'أولياء الأمور', 'حساب مرتبط بالمنصة', 'violet');
            $stats[] = $this->stat('contact_messages', ContactMessage::where('status', '!=', 'resolved')->count(), 'رسائل التواصل', 'بانتظار المتابعة أو الرد', 'cyan');
        }
        if ($user->hasAdminPermission('content.manage')) {
            $stats[] = $this->stat('courses', Course::available()->count(), 'الدورات المنشورة', 'متاحة حالياً للطلاب', 'cyan');
            $stats[] = $this->stat('drafts', Course::notArchived()->where('is_published', false)->count(), 'مسودات المحتوى', 'تحتاج مراجعة أو استكمال', 'violet');
        }
        if ($user->hasAdminPermission('enrollments.manage')) {
            $stats[] = $this->stat('enrollments', Enrollment::where('status', 'active')->count(), 'التسجيلات الفعالة', 'صلاحيات تعلم نشطة', 'blue');
        }
        if ($user->hasAdminPermission('finance.view')) {
            $stats[] = $this->stat('revenue', (float) Order::where('status', 'completed')->where('created_at', '>=', now()->subDays(30))->sum('amount'), 'إيراد آخر 30 يوماً', 'طلبات مكتملة بالجنيه', 'green', true);
            $stats[] = $this->stat('payments', ManualPaymentSubmission::whereIn('status', ['submitted', 'under_review'])->count(), 'تحويلات تنتظر القرار', 'قائمة المراجعة المالية', 'amber');
        }
        if ($user->hasAdminPermission('system.manage')) {
            $stats[] = $this->stat('incidents', ApplicationIncident::whereNull('resolved_at')->count(), 'حوادث مفتوحة', 'تحتاج متابعة تشغيلية', 'red');
            $stats[] = $this->stat('failed_jobs', Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0, 'مهام فاشلة', 'عمليات خلفية لم تكتمل', 'amber');
        }

        return $stats;
    }

    private function tasks(User $user): array
    {
        $tasks = [];

        if ($user->hasAdminPermission('payments.review')) {
            $tasks[] = $this->task('مراجعة التحويلات الجديدة', 'تحقق من المرجع والمبلغ والإيصال قبل اتخاذ القرار.', ManualPaymentSubmission::whereIn('status', ['submitted', 'under_review'])->count(), route('admin.payment-reviews.index', ['status' => 'submitted']), 'finance');
            $duplicateCount = ManualPaymentSubmission::whereNotNull('receipt_sha256')->whereIn('receipt_sha256', ManualPaymentSubmission::select('receipt_sha256')->whereNotNull('receipt_sha256')->groupBy('receipt_sha256')->havingRaw('count(*) > 1'))->count();
            $tasks[] = $this->task('إيصالات متكررة', 'تحتاج مطابقة يدوية موثقة قبل الاعتماد.', $duplicateCount, route('admin.payment-reviews.index', ['duplicates' => 1]), 'danger');
        }
        if ($user->hasAdminPermission('reconciliation.manage')) {
            $tasks[] = $this->task('تنبيهات المطابقة المالية', 'فروق مالية ما زالت دون معالجة.', ReconciliationAlert::whereNull('resolved_at')->count(), route('admin.reconciliation.index'), 'danger');
        }
        if ($user->hasAdminPermission('users.manage')) {
            $tasks[] = $this->task('رسائل تواصل بانتظار المتابعة', 'رسائل زوار أو طلاب لم تُغلق بعد.', ContactMessage::where('status', '!=', 'resolved')->count(), route('admin.contact-messages.index', ['status' => 'new']), 'communication');
            $tasks[] = $this->task('طلاب مرتفعو المخاطر', 'حالات مفتوحة تحتاج تواصلاً أو خطة متابعة.', StudentSupportCase::where('risk_level', 'high')->where('status', '!=', 'resolved')->count(), route('admin.support.index', ['risk_level' => 'high']), 'support');
            $tasks[] = $this->task('متابعات متأخرة', 'مواعيد متابعة مرّ وقت تنفيذها.', StudentSupportCase::where('status', '!=', 'resolved')->where('next_follow_up_at', '<', now())->count(), route('admin.support.index'), 'warning');
        }
        if ($user->hasAdminPermission('content.manage')) {
            $blocked = Course::notArchived()->where('is_published', false)->where(function ($query) {
                $query->whereDoesntHave('lessons')->orWhereNull('description')->orWhereNull('description_en');
            })->count();
            $tasks[] = $this->task('دورات غير جاهزة للنشر', 'محتوى ينقصه وصف ثنائي اللغة أو درس واحد على الأقل.', $blocked, route('admin.courses.index', ['status' => 'draft']), 'content');
        }
        if ($user->hasAdminPermission('announcements.manage')) {
            $tasks[] = $this->task('إعلانات مجدولة', 'رسائل تنتظر وقت النشر المحدد.', Announcement::where('status', 'scheduled')->count(), route('admin.announcements.index', ['status' => 'scheduled']), 'communication');
        }
        if ($user->hasAdminPermission('system.manage')) {
            $tasks[] = $this->task('حوادث تشغيلية مفتوحة', 'أخطاء أو مؤشرات صحة لم يتم إغلاقها.', ApplicationIncident::whereNull('resolved_at')->count(), route('admin.system.observability.index'), 'danger');
        }

        return collect($tasks)->filter(fn (array $task) => $task['count'] > 0)->sortByDesc('count')->values()->all();
    }

    private function quickActions(User $user): array
    {
        return collect([
            ['permission' => 'content.manage', 'key' => 'course', 'label' => 'إنشاء دورة', 'description' => 'ابدأ محتوى تعليمياً جديداً', 'url' => route('admin.courses.create')],
            ['permission' => 'payments.review', 'key' => 'payment', 'label' => 'مراجعة تحويل', 'description' => 'افتح قائمة المدفوعات المعلقة', 'url' => route('admin.payment-reviews.index')],
            ['permission' => 'enrollments.manage', 'key' => 'enrollment', 'label' => 'إدارة تسجيل', 'description' => 'فعّل أو عدّل وصول طالب', 'url' => route('admin.enrollments.index')],
            ['permission' => 'users.manage', 'key' => 'support', 'label' => 'متابعة طالب', 'description' => 'راجع حالات الخطر والدعم', 'url' => route('admin.support.index')],
            ['permission' => 'analytics.view', 'key' => 'analytics', 'label' => 'عرض التقارير', 'description' => 'راجع اتجاهات التعلم والأداء', 'url' => route('admin.analytics.index')],
            ['permission' => 'system.manage', 'key' => 'system', 'label' => 'فحص النظام', 'description' => 'راجع الصحة والحوادث', 'url' => route('admin.system.observability.index')],
        ])->filter(fn (array $action) => $user->hasAdminPermission($action['permission']))->take(4)->values()->all();
    }

    private function stat(string $key, int|float $value, string $label, string $context, string $tone, bool $currency = false): array
    {
        return compact('key', 'value', 'label', 'context', 'tone', 'currency');
    }

    private function task(string $title, string $description, int $count, string $url, string $tone): array
    {
        return compact('title', 'description', 'count', 'url', 'tone');
    }
}
