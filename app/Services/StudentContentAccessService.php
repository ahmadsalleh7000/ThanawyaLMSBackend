<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StudentContentAccessService
{
    public function courseIdsFor(User $user): Collection
    {
        if ($user->isAdmin()) {
            return Course::available()->pluck('id');
        }

        if (! $user->isEligibleForAccess()) {
            return collect();
        }

        $direct = $user->enrollments()->active()->pluck('course_id');
        $general = Course::available()->general()->pluck('id');
        $termIds = $user->studentSubscriptions()->active()->pluck('academic_term_id');
        $scoped = Course::available()->academic()->whereHas('academicSubject', fn (Builder $subject) => $subject->whereIn('academic_term_id', $termIds)->where('is_active', true))->pluck('id');

        return $direct->merge($general)->merge($scoped)->unique()->values();
    }

    public function visibleCoursesQuery(User $user): Builder
    {
        $query = Course::available();
        if ($user->isAdmin()) {
            return $query;
        }
        if (! $user->isEligibleForAccess()) {
            return $query->whereRaw('1 = 0');
        }

        $directIds = $user->enrollments()->active()->select('course_id');
        $termIds = $user->studentSubscriptions()->active()->select('academic_term_id');

        return $query->where(function (Builder $audience) use ($directIds, $termIds): void {
            $audience->where('scope', 'general')
                ->orWhereIn('id', $directIds)
                ->orWhere(function (Builder $academic) use ($termIds): void {
                    $academic->where('scope', 'academic')->whereHas('academicSubject', fn (Builder $subject) => $subject
                        ->where('is_active', true)->whereIn('academic_term_id', $termIds));
                });
        });
    }

    public function canAccessCourse(User $user, Course $course): bool
    {
        if (! $user->isEligibleForAccess() || ! $course->is_published || $course->archived_at) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }
        if ($course->isGeneral()) {
            return true;
        }
        if ($user->enrollments()->active()->where('course_id', $course->id)->exists()) {
            return true;
        }

        return $course->academic_subject_id !== null && $user->studentSubscriptions()->active()
            ->where('academic_term_id', $course->academicSubject()->where('is_active', true)->value('academic_term_id'))->exists();
    }

    public function canAccessLesson(User $user, Lesson $lesson): bool
    {
        $lesson->loadMissing('course');
        if (! $this->canAccessCourse($user, $lesson->course)) {
            return false;
        }

        $unitAvailable = ! $lesson->unit_id || ($lesson->unit?->status === 'published'
            && (! $lesson->unit->available_from || $lesson->unit->available_from->isPast())
            && (! $lesson->unit->available_until || $lesson->unit->available_until->isFuture()));

        return $unitAvailable && $lesson->status === 'published'
            && (! $lesson->available_from || $lesson->available_from->isPast())
            && (! $lesson->available_until || $lesson->available_until->isFuture());
    }

    public function canAccessMedia(User $user, MediaAsset $media): bool
    {
        $media->loadMissing('lesson.course');

        return $this->canAccessLesson($user, $media->lesson)
            && $media->status === 'published'
            && (! $media->available_from || $media->available_from->isPast())
            && (! $media->available_until || $media->available_until->isFuture());
    }
}
