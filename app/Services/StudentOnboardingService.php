<?php

namespace App\Services;

use App\Models\Course;
use App\Models\StudentOnboardingProfile;

class StudentOnboardingService
{
    public function questions(): array
    {
        return [
            ['id' => 'variables', 'prompt' => 'Which statement correctly creates a variable?', 'promptAr' => 'أي تعبير ينشئ متغيراً بشكل صحيح؟', 'options' => [['id' => 'a', 'label' => 'let score = 10', 'labelAr' => 'let score = 10'], ['id' => 'b', 'label' => 'score :=: 10', 'labelAr' => 'score :=: 10'], ['id' => 'c', 'label' => 'variable(10)', 'labelAr' => 'variable(10']]],
            ['id' => 'condition', 'prompt' => 'What is an if statement used for?', 'promptAr' => 'فيمَ تُستخدم جملة if؟', 'options' => [['id' => 'a', 'label' => 'Repeating forever', 'labelAr' => 'التكرار بلا توقف'], ['id' => 'b', 'label' => 'Making a decision', 'labelAr' => 'اتخاذ قرار'], ['id' => 'c', 'label' => 'Saving an image', 'labelAr' => 'حفظ صورة']]],
            ['id' => 'loop', 'prompt' => 'Which concept repeats a block of code?', 'promptAr' => 'أي مفهوم يكرر مجموعة أوامر؟', 'options' => [['id' => 'a', 'label' => 'Loop', 'labelAr' => 'حلقة تكرار'], ['id' => 'b', 'label' => 'Comment', 'labelAr' => 'تعليق'], ['id' => 'c', 'label' => 'String', 'labelAr' => 'نص']]],
            ['id' => 'function', 'prompt' => 'Why do developers use functions?', 'promptAr' => 'لماذا يستخدم المطورون الدوال؟', 'options' => [['id' => 'a', 'label' => 'To organize reusable logic', 'labelAr' => 'لتنظيم منطق قابل لإعادة الاستخدام'], ['id' => 'b', 'label' => 'Only to change colors', 'labelAr' => 'لتغيير الألوان فقط'], ['id' => 'c', 'label' => 'To delete the program', 'labelAr' => 'لحذف البرنامج']]],
        ];
    }

    public function evaluate(StudentOnboardingProfile $profile, array $answers): StudentOnboardingProfile
    {
        $correct = ['variables' => 'a', 'condition' => 'b', 'loop' => 'a', 'function' => 'a'];
        $score = collect($correct)->filter(fn ($answer, $id) => ($answers[$id] ?? null) === $answer)->count();
        $level = $score <= 1 ? 'Beginner' : ($score <= 3 ? 'Intermediate' : 'Advanced');
        $ranked = fn () => Course::available()->where('grade', $profile->grade)
            ->orderByRaw('CASE WHEN level = ? THEN 0 ELSE 1 END', [$level])->orderBy('price');
        $course = $ranked()->where('semester', $profile->semester)->first() ?? $ranked()->first();
        $profile->update(['placement_answers' => $answers, 'placement_score' => $score, 'recommended_level' => $level, 'recommended_course_id' => $course?->id, 'completed_at' => now()]);

        return $profile->refresh()->load('recommendedCourse:id,title,title_en,summary,summary_en,level');
    }
}
