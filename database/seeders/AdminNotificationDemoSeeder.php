<?php

namespace Database\Seeders;

use App\Models\ManualPaymentSubmission;
use App\Models\PlatformSetting;
use App\Models\User;
use Database\Seeders\Concerns\EnsuresNonProductionSeedEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminNotificationDemoSeeder extends Seeder
{
    use EnsuresNonProductionSeedEnvironment;

    public const MARKER = 'testing.admin_notification_seed.v1';

    public function run(): void
    {
        $this->ensureNonProductionSeedEnvironment();

        if (PlatformSetting::where('key', self::MARKER)->exists()) {
            $this->command?->info('Admin notification demo data was already seeded; nothing was changed.');

            return;
        }

        $admins = User::where('role', User::ROLE_ADMIN)->get();
        if ($admins->isEmpty()) {
            $this->command?->warn('No administrators exist; notification demo data was not seeded.');

            return;
        }

        $paymentReview = ManualPaymentSubmission::latest('id')->first();

        DB::transaction(function () use ($admins, $paymentReview): void {
            foreach ($admins as $admin) {
                foreach ($this->definitions($admin, $paymentReview) as $notification) {
                    $admin->userNotifications()->create($notification);
                }
            }

            PlatformSetting::create([
                'key' => self::MARKER,
                'value' => ['version' => 1, 'seeded_at' => now()->toISOString()],
                'group' => 'testing',
            ]);
        });

        $this->command?->info('Role-aware admin notification demo data seeded successfully.');
    }

    /** @return array<int, array<string, mixed>> */
    private function definitions(User $admin, ?ManualPaymentSubmission $paymentReview): array
    {
        $items = [[
            'type' => 'security_notice',
            'title' => 'Protected admin session verified',
            'title_ar' => 'تم التحقق من أمان الجلسة الإدارية',
            'message' => 'Your latest administrative sign-in passed the security checks.',
            'message_ar' => 'اجتاز آخر تسجيل دخول إداري فحوصات الأمان بنجاح.',
            'action_url' => '/admin',
            'data' => ['seed_key' => 'security-session'],
            'read_at' => now()->subMinutes(30),
        ]];

        if ($admin->hasAdminPermission('payments.review')) {
            $action = $paymentReview ? '/admin/payment-reviews/'.$paymentReview->id : '/admin/payment-reviews';
            $items[] = [
                'type' => 'student_registration',
                'title' => 'New student registration awaiting review',
                'title_ar' => 'تسجيل طالب جديد بانتظار المراجعة',
                'message' => 'A student submitted registration details and a payment receipt.',
                'message_ar' => 'أرسل طالب جديد بيانات التسجيل وإيصال التحويل للمراجعة.',
                'action_url' => $action,
                'data' => ['seed_key' => 'registration-review'],
                'read_at' => null,
            ];
            $items[] = [
                'type' => 'payment_review',
                'title' => 'Payment evidence needs a decision',
                'title_ar' => 'إثبات دفع يحتاج إلى قرار',
                'message' => 'The submitted transaction details are ready for comparison.',
                'message_ar' => 'بيانات التحويل المرسلة جاهزة للمقارنة واتخاذ القرار.',
                'action_url' => $action,
                'data' => ['seed_key' => 'payment-decision'],
                'read_at' => null,
            ];
        }

        if ($admin->hasAdminPermission('content.manage')) {
            $items[] = [
                'type' => 'course_readiness',
                'title' => 'A draft course needs content',
                'title_ar' => 'دورة مسودة تحتاج إلى استكمال المحتوى',
                'message' => 'Review the lessons and assessment before publishing.',
                'message_ar' => 'راجع الدروس والتقييم قبل إتاحة الدورة للطلاب.',
                'action_url' => '/admin/courses',
                'data' => ['seed_key' => 'course-readiness'],
                'read_at' => null,
            ];
        }

        if ($admin->hasAdminPermission('users.manage')) {
            $items[] = [
                'type' => 'student_support',
                'title' => 'Student follow-up is overdue',
                'title_ar' => 'متابعة طالب متأخرة',
                'message' => 'A high-priority student support case needs an update.',
                'message_ar' => 'توجد حالة دعم ذات أولوية مرتفعة تحتاج إلى تحديث.',
                'action_url' => '/admin/support',
                'data' => ['seed_key' => 'support-follow-up'],
                'read_at' => null,
            ];
        }

        if ($admin->hasAdminPermission('system.manage')) {
            $items[] = [
                'type' => 'system_alert',
                'title' => 'Queue health requires attention',
                'title_ar' => 'صحة طابور المهام تحتاج إلى انتباه',
                'message' => 'Review the latest operations and worker heartbeat.',
                'message_ar' => 'راجع حالة التشغيل وآخر نبضة لعامل المهام.',
                'action_url' => '/admin/system/observability',
                'data' => ['seed_key' => 'system-health'],
                'read_at' => null,
            ];
        }

        return $items;
    }
}
