<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\ApplicationIncident;
use App\Models\Course;
use App\Models\ManualPaymentSubmission;
use App\Models\Order;
use App\Models\PaymentReconciliationPeriod;
use App\Models\PlatformSetting;
use App\Models\ReconciliationAlert;
use App\Models\StudentSupportCase;
use App\Models\User;
use Database\Seeders\Concerns\EnsuresNonProductionSeedEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminDashboardTestingSeeder extends Seeder
{
    use EnsuresNonProductionSeedEnvironment;

    public const MARKER = 'testing.admin_dashboard_seed.v1';

    public function run(): void
    {
        $this->ensureNonProductionSeedEnvironment();

        if (PlatformSetting::where('key', self::MARKER)->exists()) {
            $this->command?->info('Testing dashboard seed data already exists; nothing was changed.');

            return;
        }

        DB::transaction(function (): void {
            $staff = $this->staff();
            $students = $this->students();
            $courses = $this->courses();

            $this->payments($students, $courses, $staff['finance']);
            $this->studentSupport($students, $staff['support']);
            $this->operations($staff['super']);
            $this->communication($staff['support'], $courses['published']);

            PlatformSetting::create([
                'key' => self::MARKER,
                'value' => ['version' => 1, 'seeded_at' => now()->toISOString()],
                'group' => 'testing',
            ]);
        });
    }

    private function staff(): array
    {
        $definitions = [
            'super' => ['name' => 'مدير الاختبار', 'email' => 'admin.dashboard@test.local', 'admin_role' => 'super_admin'],
            'finance' => ['name' => 'مدير المالية التجريبي', 'email' => 'finance.dashboard@test.local', 'admin_role' => 'finance_manager'],
            'reviewer' => ['name' => 'مراجع التحويلات التجريبي', 'email' => 'reviewer.dashboard@test.local', 'admin_role' => 'finance_reviewer'],
            'content' => ['name' => 'محرر المحتوى التجريبي', 'email' => 'content.dashboard@test.local', 'admin_role' => 'content_editor'],
            'support' => ['name' => 'دعم الطلاب التجريبي', 'email' => 'support.dashboard@test.local', 'admin_role' => 'support_agent'],
        ];

        return collect($definitions)->map(fn (array $definition) => User::updateOrCreate(
            ['email' => $definition['email']],
            $definition + ['password' => 'TestingPassword123!', 'role' => User::ROLE_ADMIN, 'is_active' => true],
        ))->all();
    }

    private function students(): array
    {
        return collect(range(1, 4))->map(fn (int $number) => User::updateOrCreate(
            ['email' => "dashboard.student{$number}@test.local"],
            ['name' => "طالب لوحة التحكم {$number}", 'password' => 'TestingPassword123!', 'role' => User::ROLE_STUDENT, 'is_active' => true],
        ))->all();
    }

    private function courses(): array
    {
        $published = Course::updateOrCreate(['id' => 'dashboard-published'], [
            'title' => 'مسار البرمجة التجريبي', 'title_en' => 'Testing programming path',
            'summary' => 'دورة لاختبار مؤشرات الإدارة.', 'summary_en' => 'A course for dashboard testing.',
            'description' => 'محتوى مكتمل وجاهز للطلاب.', 'description_en' => 'Complete content ready for students.',
            'category' => 'Programming', 'grade' => 'grade-1', 'semester' => 'semester-1',
            'level' => 'Beginner', 'duration_minutes' => 180, 'price' => 450,
            'instructor' => 'مدرس تجريبي', 'instructor_en' => 'Testing Instructor', 'is_published' => true,
        ]);
        $published->lessons()->firstOrCreate(['slug' => 'dashboard-intro'], ['title' => 'مقدمة تجريبية', 'title_en' => 'Testing introduction', 'duration_minutes' => 20, 'position' => 1]);

        foreach (range(1, 2) as $number) {
            Course::updateOrCreate(['id' => "dashboard-draft-{$number}"], [
                'title' => "مسودة تحتاج استكمال {$number}", 'title_en' => "Incomplete draft {$number}",
                'summary' => 'مسودة لاختبار قائمة الجاهزية.', 'summary_en' => 'Draft for readiness testing.',
                'description' => null, 'description_en' => null, 'category' => 'Programming',
                'grade' => 'grade-2', 'semester' => 'semester-1', 'level' => 'Intermediate',
                'duration_minutes' => 120, 'price' => 300, 'instructor' => 'مدرس تجريبي',
                'instructor_en' => 'Testing Instructor', 'is_published' => false,
            ]);
        }

        return ['published' => $published];
    }

    private function payments(array $students, array $courses, User $finance): void
    {
        foreach (array_slice($students, 0, 3) as $index => $student) {
            $reference = 'TEST-DASH-'.($index + 1);
            $order = Order::create([
                'public_id' => (string) Str::uuid(), 'user_id' => $student->id,
                'course_id' => $courses['published']->id, 'original_amount' => 450,
                'amount' => 450, 'currency' => 'EGP', 'status' => 'submitted',
                'payment_provider' => 'manual_transfer', 'payment_method' => $index ? 'vodafone_cash' : 'instapay',
                'session_token_hash' => hash('sha256', "dashboard-session-{$index}"),
            ]);
            ManualPaymentSubmission::create([
                'order_id' => $order->id, 'user_id' => $student->id,
                'channel' => $index ? 'vodafone_cash' : 'instapay', 'transaction_id' => $reference,
                'sender_identifier' => '0100000000'.($index + 1), 'transferred_at' => now()->subMinutes(20 + $index),
                'submitted_amount' => 450, 'receipt_path' => "testing/{$reference}.jpg",
                'receipt_original_name' => "receipt-{$index}.jpg", 'receipt_mime' => 'image/jpeg',
                'receipt_size' => 128000, 'receipt_sha256' => $index < 2 ? str_repeat('a', 64) : hash('sha256', $reference),
                'status' => $index === 2 ? 'under_review' : 'submitted',
                'reviewed_by' => $index === 2 ? $finance->id : null,
            ]);
        }

        Order::create([
            'public_id' => (string) Str::uuid(), 'user_id' => $students[3]->id,
            'course_id' => $courses['published']->id, 'original_amount' => 450, 'amount' => 450,
            'currency' => 'EGP', 'status' => 'completed', 'payment_provider' => 'manual_transfer',
            'payment_method' => 'instapay', 'session_token_hash' => hash('sha256', 'dashboard-completed'),
            'provider_payment_id' => 'TEST-COMPLETED-1', 'paid_at' => now()->subDays(2),
        ]);
    }

    private function studentSupport(array $students, User $support): void
    {
        StudentSupportCase::create(['student_id' => $students[0]->id, 'assigned_to' => $support->id, 'status' => 'open', 'risk_level' => 'high', 'risk_score' => 88, 'risk_signals' => [['key' => 'low_progress', 'label' => 'تقدم تعليمي منخفض'], ['key' => 'inactive', 'label' => 'انخفاض النشاط مؤخراً']], 'detected_at' => now()->subDays(5), 'next_follow_up_at' => now()->subDay()]);
        StudentSupportCase::create(['student_id' => $students[1]->id, 'assigned_to' => $support->id, 'status' => 'monitoring', 'risk_level' => 'high', 'risk_score' => 76, 'risk_signals' => [['key' => 'failed_assessments', 'label' => 'تعثر في التقييمات']], 'detected_at' => now()->subDays(3), 'next_follow_up_at' => now()->addDay()]);
        StudentSupportCase::create(['student_id' => $students[2]->id, 'status' => 'open', 'risk_level' => 'medium', 'risk_score' => 55, 'risk_signals' => [['key' => 'inactive', 'label' => 'انخفاض النشاط مؤخراً']], 'detected_at' => now()->subDays(2), 'next_follow_up_at' => now()->subHours(3)]);
    }

    private function operations(User $super): void
    {
        ApplicationIncident::create(['fingerprint' => hash('sha256', 'testing-dashboard-incident'), 'type' => 'queue', 'severity' => 'critical', 'title' => 'توقف عامل المهام التجريبي', 'message' => 'حادث تجريبي ظاهر في لوحة الإدارة.', 'context' => ['testing' => true], 'occurrences' => 2, 'first_seen_at' => now()->subHour(), 'last_seen_at' => now()->subMinutes(10)]);
        $period = PaymentReconciliationPeriod::create(['name' => 'مطابقة تجريبية', 'starts_on' => now()->subWeek()->toDateString(), 'ends_on' => now()->toDateString(), 'status' => 'open', 'created_by' => $super->id]);
        ReconciliationAlert::create(['period_id' => $period->id, 'type' => 'inflow_variance', 'channel' => 'instapay', 'severity' => 'critical', 'amount' => 450, 'message' => 'فرق تجريبي يحتاج مراجعة.', 'detected_at' => now()->subMinutes(30)]);
    }

    private function communication(User $support, Course $course): void
    {
        Announcement::create(['created_by' => $support->id, 'title' => 'Testing announcement', 'title_ar' => 'إعلان تجريبي مجدول', 'message' => 'Dashboard testing announcement.', 'message_ar' => 'إعلان لاختبار قائمة العمل.', 'target_type' => 'course', 'course_id' => $course->id, 'status' => 'scheduled', 'publish_at' => now()->addDay()]);
    }
}
