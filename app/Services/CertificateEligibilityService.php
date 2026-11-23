<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CertificateEligibilityService
{
    public function status(User $student, Course $course): array
    {
        $lessonIds = $course->lessons()->where('status', 'published')
            ->where(fn ($query) => $query->whereNull('unit_id')->orWhereHas('unit', fn ($unit) => $unit->where('status', 'published')))
            ->pluck('id');
        $completed = $student->lessonCompletions()->whereIn('lesson_id', $lessonIds)->count();
        $passed = $student->assessmentAttempts()->where('passed', true)
            ->whereHas('assessment', fn ($query) => $query->where('course_id', $course->id))->exists();
        $certificate = $student->certificates()->where('course_id', $course->id)->first();

        return [
            'lessonsTotal' => $lessonIds->count(), 'lessonsCompleted' => $completed,
            'lessonsComplete' => $lessonIds->isNotEmpty() && $completed === $lessonIds->count(),
            'assessmentPassed' => $passed,
            'certificateId' => $certificate && ! $certificate->revoked_at ? $certificate->public_id : null,
            'revoked' => (bool) $certificate?->revoked_at,
        ];
    }

    public function ensureIssued(User $student, Course $course): ?Certificate
    {
        return DB::transaction(function () use ($student, $course) {
            User::whereKey($student->id)->lockForUpdate()->firstOrFail();
            if ($existing = $student->certificates()->where('course_id', $course->id)->first()) {
                return $existing->revoked_at ? null : $existing;
            }
            $status = $this->status($student, $course);
            if (! $status['lessonsComplete'] || ! $status['assessmentPassed']) {
                return null;
            }
            $attempt = $student->assessmentAttempts()->where('passed', true)
                ->whereHas('assessment', fn ($query) => $query->where('course_id', $course->id))
                ->latest('submitted_at')->firstOrFail();

            return Certificate::firstOrCreate(['user_id' => $student->id, 'course_id' => $course->id], [
                'public_id' => (string) Str::uuid(), 'assessment_attempt_id' => $attempt->id, 'issued_at' => now(),
            ]);
        });
    }
}
