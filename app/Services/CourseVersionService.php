<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseVersion;
use App\Models\User;

class CourseVersionService
{
    private const RESTORABLE = ['title', 'title_en', 'summary', 'summary_en', 'description', 'description_en', 'category', 'scope', 'academic_subject_id', 'grade', 'semester', 'unit', 'track_type', 'level', 'duration_minutes', 'price', 'instructor', 'instructor_en', 'instructor_profile_id'];

    public function capture(Course $course, ?User $actor, string $event, ?string $label = null): CourseVersion
    {
        $course->loadMissing(['lessons.mediaAssets', 'assessment.questions']);

        return $course->versions()->create([
            'created_by' => $actor?->id, 'event' => $event, 'label' => $label,
            'snapshot' => ['course' => $course->attributesToArray(), 'lessons' => $course->lessons->map(fn ($lesson) => $lesson->attributesToArray() + ['media' => $lesson->mediaAssets->map->attributesToArray()->all()])->all(), 'assessment' => $course->assessment ? $course->assessment->attributesToArray() + ['questions' => $course->assessment->questions->map->attributesToArray()->all()] : null],
        ]);
    }

    public function restoreMetadata(Course $course, CourseVersion $version, User $actor): void
    {
        abort_unless($version->course_id === $course->id, 404);
        $this->capture($course, $actor, 'before_restore', 'نسخة أمان قبل الاستعادة');
        $course->update(collect($version->snapshot['course'])->only(self::RESTORABLE)->all());
        $version->update(['restored_at' => now()]);
    }
}
