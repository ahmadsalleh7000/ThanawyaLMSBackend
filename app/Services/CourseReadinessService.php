<?php

namespace App\Services;

use App\Models\Course;

class CourseReadinessService
{
    public function inspect(Course $course): array
    {
        $course->loadMissing(['lessons.mediaAssets.libraryItem', 'assessment.questions']);
        $lessons = $course->lessons;
        $items = [
            $this->item('identity', 'بيانات الدورة ثنائية اللغة', $this->filled($course, ['title', 'title_en', 'summary', 'summary_en', 'instructor', 'instructor_en']), 'blocking', 'أكمل العناوين والملخصات وبيانات المدرس بالعربية والإنجليزية.'),
            $this->item('description', 'الوصف التفصيلي', filled($course->description) && filled($course->description_en), 'warning', 'أضف وصفاً تفصيلياً بالعربية والإنجليزية لتحسين قرار التسجيل.'),
            $this->item('curriculum', 'محتوى تعليمي', $lessons->isNotEmpty(), 'blocking', 'أضف درساً واحداً على الأقل قبل النشر.'),
            $this->item('duration', 'مدة الدورة', $course->duration_minutes > 0 && $lessons->every(fn ($lesson) => $lesson->duration_minutes > 0), 'blocking', 'حدد مدة صحيحة للدورة ولكل درس.'),
            $this->item('preview', 'درس معاينة مجاني', $lessons->contains('is_preview', true), 'warning', 'حدد درس معاينة لمساعدة الطلاب على تقييم الدورة.'),
            $this->item('media', 'وسائط الدروس', $lessons->isNotEmpty() && $lessons->every(fn ($lesson) => filled($lesson->video_url) || $lesson->mediaAssets->where('kind', 'video')->contains(fn ($asset) => ! $asset->libraryItem || ($asset->libraryItem->processing_status === 'ready' && ! $asset->libraryItem->archived_at))), 'warning', 'بعض الدروس لا تحتوي على فيديو داخلي أو خارجي جاهز.'),
            $this->item('assessment', 'تقييم جاهز', $course->assessment?->is_published && $course->assessment->questions->isNotEmpty(), 'warning', 'أنشئ تقييماً بأسئلة وانشره لقياس فهم الطلاب.'),
            $this->item('availability', 'حالة الدورة', ! $course->archived_at, 'blocking', 'استعد الدورة من الأرشيف قبل نشرها.'),
        ];
        $blockers = collect($items)->where('status', 'blocking')->count();
        $warnings = collect($items)->where('status', 'warning')->count();
        $ready = collect($items)->where('status', 'ready')->count();

        return [
            'items' => $items,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'ready' => $ready,
            'total' => count($items),
            'score' => (int) round(($ready / count($items)) * 100),
            'canPublish' => $blockers === 0,
        ];
    }

    private function item(string $key, string $label, bool $passes, string $failureStatus, string $guidance): array
    {
        return ['key' => $key, 'label' => $label, 'status' => $passes ? 'ready' : $failureStatus, 'guidance' => $passes ? 'جاهز للنشر.' : $guidance];
    }

    private function filled(Course $course, array $attributes): bool
    {
        return collect($attributes)->every(fn (string $attribute) => filled($course->{$attribute}));
    }
}
