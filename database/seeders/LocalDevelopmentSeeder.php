<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\PlatformSetting;
use App\Models\User;
use Database\Seeders\Concerns\EnsuresNonProductionSeedEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocalDevelopmentSeeder extends Seeder
{
    use EnsuresNonProductionSeedEnvironment;

    public const MARKER = 'local.frontend_seed.v1';

    public function run(): void
    {
        $this->ensureNonProductionSeedEnvironment();

        if (PlatformSetting::where('key', self::MARKER)->exists()) {
            $this->command?->info('Local frontend data was already seeded; nothing was changed.');

            return;
        }

        DB::transaction(function (): void {
            $users = $this->users();
            $courses = collect($this->courseDefinitions())->mapWithKeys(function (array $definition): array {
                $lessons = $definition['lessons'];
                unset($definition['lessons']);
                $course = Course::updateOrCreate(['id' => $definition['id']], $definition);
                foreach ($lessons as $lesson) {
                    $course->lessons()->updateOrCreate(['slug' => $lesson['slug']], $lesson);
                }

                return [$course->id => $course];
            });

            $this->assessment($courses['python-1']);
            $users['student']->enrollments()->firstOrCreate(
                ['course_id' => 'python-1'],
                ['enrolled_at' => now()],
            );
            $users['parent']->linkedStudents()->syncWithoutDetaching([$users['student']->id => [
                'grade' => 'grade-1',
                'linked_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]]);

            PlatformSetting::create([
                'key' => self::MARKER,
                'value' => ['version' => 1, 'seeded_at' => now()->toISOString()],
                'group' => 'local',
            ]);
        });

        $this->command?->info('Local frontend courses and accounts were seeded successfully.');
    }

    /** @return array{student: User, parent: User, admin: User} */
    private function users(): array
    {
        $users = [
            'student' => User::firstOrCreate(['email' => 'student@ahmed-lms.local'], ['name' => 'Ahmed Student', 'password' => 'password123', 'role' => User::ROLE_STUDENT]),
            'parent' => User::firstOrCreate(['email' => 'parent@ahmed-lms.local'], ['name' => 'Ahmed Parent', 'password' => 'password123', 'role' => User::ROLE_PARENT]),
            'admin' => User::firstOrCreate(['email' => 'admin@ahmed-lms.local'], ['name' => 'Local Administrator', 'password' => 'password123', 'role' => User::ROLE_ADMIN, 'admin_role' => 'super_admin']),
        ];
        collect($users)->each(function (User $user): void {
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
        });

        return $users;
    }

    /** @return array<int, array<string, mixed>> */
    private function courseDefinitions(): array
    {
        return [
            $this->course('python-1', [
                'title' => 'بايثون من البداية',
                'title_en' => 'Python from the Beginning',
                'summary' => 'ابدأ بأساسيات البرمجة وابنِ مشاريع صغيرة خطوة بخطوة.',
                'summary_en' => 'Master programming foundations through practical Python projects.',
                'category' => 'Programming',
                'instructor' => 'أحمد النجار',
                'instructor_en' => 'Ahmed Al-Naggar',
                'price' => 0,
            ], [
                $this->lesson('intro-python', 'مقدمة إلى بايثون', 'Introduction to Python', 18, 1, true),
                $this->lesson('variables', 'المتغيرات وأنواع البيانات', 'Variables and data types', 24, 2),
                $this->lesson('conditions', 'الشروط واتخاذ القرار', 'Conditions and decisions', 26, 3),
            ]),
            $this->course('web-1', [
                'title' => 'أساسيات تطوير الويب',
                'title_en' => 'Web Development Foundations',
                'summary' => 'تعلّم HTML وCSS وأنشئ صفحات متجاوبة.',
                'summary_en' => 'Create responsive pages with modern HTML and CSS.',
                'category' => 'Web Development',
                'semester' => 'semester-2',
                'instructor' => 'سارة محمود',
                'instructor_en' => 'Sara Mahmoud',
                'price' => 250,
            ], [
                $this->lesson('web-intro', 'كيف يعمل الويب؟', 'How the web works', 16, 1, true),
                $this->lesson('html', 'بناء الصفحة باستخدام HTML', 'Building with HTML', 28, 2),
                $this->lesson('css', 'التنسيق باستخدام CSS', 'Styling with CSS', 31, 3),
            ]),
            $this->course('nodejs-1', [
                'title' => 'تطوير الخلفية باستخدام Node.js',
                'title_en' => 'Backend Development with Node.js',
                'summary' => 'أنشئ واجهات API وخدمات خلفية منظمة.',
                'summary_en' => 'Build structured APIs and backend services.',
                'category' => 'Backend',
                'grade' => 'grade-2',
                'semester' => 'semester-2',
                'level' => 'Intermediate',
                'track_type' => 'academy-extension',
                'instructor' => 'عمر شريف',
                'instructor_en' => 'Omar Sherif',
                'price' => 400,
            ], [
                $this->lesson('node-intro', 'مقدمة إلى Node.js', 'Introduction to Node.js', 22, 1, true),
                $this->lesson('express', 'بناء API باستخدام Express', 'Building APIs with Express', 35, 2),
                $this->lesson('database', 'ربط قاعدة البيانات', 'Connecting a database', 34, 3),
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $lessons
     * @return array<string, mixed>
     */
    private function course(string $id, array $attributes, array $lessons): array
    {
        return $attributes + [
            'id' => $id,
            'description' => $attributes['summary'],
            'description_en' => $attributes['summary_en'],
            'grade' => 'grade-1',
            'semester' => 'semester-1',
            'track_type' => 'curriculum-support',
            'level' => 'Beginner',
            'duration_minutes' => array_sum(array_column($lessons, 'duration_minutes')),
            'is_published' => true,
            'lessons' => $lessons,
        ];
    }

    private function lesson(string $slug, string $title, string $titleEn, int $duration, int $position, bool $preview = false): array
    {
        return ['slug' => $slug, 'title' => $title, 'title_en' => $titleEn, 'duration_minutes' => $duration, 'position' => $position, 'is_preview' => $preview];
    }

    private function assessment(Course $course): void
    {
        $assessment = Assessment::updateOrCreate(['course_id' => $course->id], ['duration_minutes' => 10, 'passing_score' => 70, 'is_published' => true]);
        $questions = [
            ['topic' => 'Python basics', 'topic_ar' => 'أساسيات بايثون', 'prompt' => 'Which function prints text?', 'prompt_ar' => 'ما الدالة التي تطبع النص؟', 'options' => ['print()', 'show()', 'write()'], 'options_ar' => ['print()', 'show()', 'write()'], 'correct_option' => 0, 'explanation' => 'print() writes to standard output.', 'explanation_ar' => 'تكتب print() في المخرجات القياسية.', 'position' => 1],
            ['topic' => 'Variables', 'topic_ar' => 'المتغيرات', 'prompt' => 'Which assignment is valid?', 'prompt_ar' => 'أي إسناد صحيح؟', 'options' => ['score = 10', 'let score = 10', 'score :=: 10'], 'options_ar' => ['score = 10', 'let score = 10', 'score :=: 10'], 'correct_option' => 0, 'explanation' => 'Python uses equals for assignment.', 'explanation_ar' => 'تستخدم بايثون علامة المساواة للإسناد.', 'position' => 2],
        ];
        foreach ($questions as $question) {
            $assessment->questions()->updateOrCreate(['position' => $question['position']], $question);
        }
    }
}
