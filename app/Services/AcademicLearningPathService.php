<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\AssessmentAttempt;
use App\Models\LessonCompletion;
use App\Models\TrainingAttempt;
use App\Models\User;

class AcademicLearningPathService
{
    public function curriculum(AcademicTerm $term): array
    {
        $term->load([
            'academicYear', 'grade',
            'academicSubjects' => fn ($query) => $query->where('is_active', true)->orderBy('display_order')->orderBy('id'),
            'academicSubjects.subject',
            'academicSubjects.courses' => fn ($query) => $query->where('scope', 'academic')->whereNull('archived_at')->orderBy('position')->orderBy('title'),
            'academicSubjects.courses.units.lessons.mediaAssets',
            'academicSubjects.courses.units.lessons.trainings.questions',
            'academicSubjects.courses.units.trainings.questions',
            'academicSubjects.courses.trainings.questions',
            'academicSubjects.courses.assessment.questions',
        ]);
        $courses = $term->academicSubjects->flatMap->courses;
        $units = $courses->flatMap->units;
        $lessons = $courses->flatMap(fn ($course) => $course->units->flatMap->lessons);
        $resources = $lessons->flatMap->mediaAssets;
        $trainings = $courses->flatMap(fn ($course) => $course->trainings
            ->merge($course->units->flatMap->trainings)
            ->merge($course->units->flatMap(fn ($unit) => $unit->lessons->flatMap->trainings)));

        return ['term' => $term, 'summary' => [
            'courses' => $courses->count(), 'units' => $units->count(), 'lessons' => $lessons->count(),
            'publishedLessons' => $lessons->where('status', 'published')->count(),
            'videos' => $resources->where('kind', 'video')->count(), 'resources' => $resources->where('kind', '!=', 'video')->count(),
            'trainings' => $trainings->count(), 'assessments' => $courses->whereNotNull('assessment')->count(),
        ]];
    }

    public function progress(AcademicTerm $term): array
    {
        $curriculum = $this->curriculum($term);
        $studentIds = $term->studentSubscriptions()->active()->distinct()->pluck('student_id');
        $students = $studentIds->count();
        $courseRows = $curriculum['term']->academicSubjects->flatMap->courses->map(function ($course) use ($studentIds, $students): array {
            $lessonIds = $course->units->flatMap->lessons->where('status', 'published')->pluck('id');
            $possible = max(1, $students * $lessonIds->count());
            $completed = LessonCompletion::whereIn('user_id', $studentIds)->whereIn('lesson_id', $lessonIds)->count();
            $attempts = AssessmentAttempt::whereIn('user_id', $studentIds)->whereHas('assessment', fn ($query) => $query->where('course_id', $course->id));
            $trainingIds = $course->trainings->pluck('id')->merge($course->units->flatMap->trainings->pluck('id'))->merge($course->units->flatMap(fn ($unit) => $unit->lessons->flatMap->trainings)->pluck('id'));
            return ['course' => $course, 'students' => $students, 'progress' => $lessonIds->isEmpty() ? 0 : round($completed / $possible * 100),
                'trainingCompletion' => $trainingIds->isEmpty() || ! $students ? 0 : round(TrainingAttempt::whereIn('student_id', $studentIds)->whereIn('training_id', $trainingIds)->where('status', 'submitted')->count() / ($students * $trainingIds->count()) * 100),
                'averageScore' => round((float) ($attempts->avg('score') ?? 0), 1)];
        })->values();

        return $curriculum + ['students' => $students, 'courses' => $courseRows,
            'averageProgress' => round((float) $courseRows->avg('progress'), 1),
            'trainingCompletion' => round((float) $courseRows->avg('trainingCompletion'), 1),
            'averageScore' => round((float) $courseRows->avg('averageScore'), 1)];
    }

    public function individualProgress(AcademicTerm $term, User $student): array
    {
        $curriculum = $this->curriculum($term);
        $completedLessonIds = LessonCompletion::where('user_id', $student->id)->pluck('lesson_id');
        $trainingAttempts = TrainingAttempt::where('student_id', $student->id)
            ->where('status', 'submitted')->get()->keyBy('training_id');
        $courseRows = $curriculum['term']->academicSubjects->flatMap->courses->map(function ($course) use ($completedLessonIds, $trainingAttempts, $student): array {
            $units = $course->units->map(function ($unit) use ($completedLessonIds, $trainingAttempts): array {
                $lessons = $unit->lessons->map(function ($lesson) use ($completedLessonIds, $trainingAttempts): array {
                    $trainings = $lesson->trainings->map(fn ($training) => [
                        'training' => $training,
                        'attempt' => $trainingAttempts->get($training->id),
                    ]);
                    return ['lesson' => $lesson, 'completed' => $completedLessonIds->contains($lesson->id), 'trainings' => $trainings];
                });
                $published = $lessons->filter(fn ($row) => $row['lesson']->status === 'published');
                return ['unit' => $unit, 'lessons' => $lessons,
                    'progress' => $published->isEmpty() ? 0 : round($published->where('completed', true)->count() / $published->count() * 100)];
            });
            $publishedLessons = $units->flatMap(fn ($row) => $row['lessons'])->filter(fn ($row) => $row['lesson']->status === 'published');
            $assessmentScore = AssessmentAttempt::where('user_id', $student->id)
                ->whereHas('assessment', fn ($query) => $query->where('course_id', $course->id))->latest('submitted_at')->value('score');
            return ['course' => $course, 'units' => $units,
                'progress' => $publishedLessons->isEmpty() ? 0 : round($publishedLessons->where('completed', true)->count() / $publishedLessons->count() * 100),
                'assessmentScore' => $assessmentScore];
        })->values();

        return $curriculum + ['student' => $student, 'individual' => true, 'courses' => $courseRows,
            'averageProgress' => round((float) $courseRows->avg('progress'), 1)];
    }
}
