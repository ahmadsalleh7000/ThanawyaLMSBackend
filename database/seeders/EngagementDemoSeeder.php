<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\User;
use Database\Seeders\Concerns\EnsuresNonProductionSeedEnvironment;
use Illuminate\Database\Seeder;

class EngagementDemoSeeder extends Seeder
{
    use EnsuresNonProductionSeedEnvironment;

    public function run(): void
    {
        $this->ensureNonProductionSeedEnvironment();
        $student = User::whereIn('email', ['student@ahmed-lms.local', 'student@ahmed-lms.test'])->first();
        $course = Course::where('id', 'nodejs-1')->where('is_published', true)->first();
        if (! $student) {
            return;
        }

        if ($course) {
            $student->wishlistItems()->firstOrCreate(['course_id' => $course->id]);
        }

        $student->userNotifications()->firstOrCreate(
            ['type' => 'course', 'title' => 'Your next lesson is ready'],
            ['title_ar' => 'درسك التالي جاهز', 'message' => 'Continue your Python path from where you stopped.', 'message_ar' => 'واصل مسار Python من حيث توقفت.', 'action_url' => '/my-learning'],
        );
    }
}
