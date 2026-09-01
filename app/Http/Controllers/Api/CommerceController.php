<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicGrade;
use App\Models\Course;
use App\Models\CourseBundle;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class CommerceController extends Controller
{
    public function bundles(): JsonResponse
    {
        $bundles = CourseBundle::where('is_active', true)
            ->whereHas('courses', fn ($query) => $query->where('is_published', true)->whereNull('archived_at'))
            ->whereDoesntHave('courses', fn ($query) => $query->where('is_published', false)->orWhereNotNull('archived_at'))
            ->with(['courses' => fn ($query) => $query->where('is_published', true)->whereNull('archived_at')])
            ->latest()->get();

        return response()->json(['data' => $bundles->map(fn ($bundle) => $this->serialize($bundle))]);
    }

    public function bundle(CourseBundle $bundle): JsonResponse
    {
        abort_unless($bundle->is_active, 404);
        $courseCount = $bundle->courses()->count();
        $bundle->load(['courses' => fn ($query) => $query->where('is_published', true)->whereNull('archived_at')->withCount('lessons')]);
        abort_if($bundle->courses->isEmpty() || $bundle->courses->count() !== $courseCount, 404);

        return response()->json(['data' => $this->serialize($bundle)]);
    }

    public function learningPaths(): JsonResponse
    {
        $grades = AcademicGrade::where('is_active', true)
            ->orderBy('display_order')
            ->get()
            ->keyBy('code');

        $coursesByGrade = Course::available()
            ->academic()
            ->whereNotNull('grade')
            ->withCount('lessons')
            ->orderBy('grade')
            ->orderBy('semester')
            ->orderBy('position')
            ->orderBy('title')
            ->get()
            ->groupBy('grade');

        $paths = $grades->filter(fn (AcademicGrade $grade) => $coursesByGrade->has($grade->code))
            ->map(fn (AcademicGrade $grade) => $this->serializeAcademicPath($grade, $coursesByGrade->get($grade->code)))
            ->values();

        return response()->json(['data' => $paths]);
    }

    public function learningPath(string $path): JsonResponse
    {
        $grade = AcademicGrade::where('code', $path)->where('is_active', true)->firstOrFail();
        $courses = Course::available()
            ->academic()
            ->where('grade', $grade->code)
            ->withCount('lessons')
            ->orderBy('semester')
            ->orderBy('position')
            ->orderBy('title')
            ->get();

        abort_if($courses->isEmpty(), 404);

        return response()->json(['data' => $this->serializeAcademicPath($grade, $courses)]);
    }

    private function serialize(CourseBundle $bundle): array
    {
        return [
            'id' => $bundle->id,
            'type' => 'learning-path',
            'name' => $bundle->name,
            'nameEn' => $bundle->name_en,
            'description' => $bundle->description,
            'descriptionEn' => $bundle->description_en,
            'courseCount' => $bundle->courses->count(),
            'durationMinutes' => (int) $bundle->courses->sum('duration_minutes'),
            'courses' => $bundle->courses->values()->map(fn (Course $course, int $index) => [
                'id' => $course->id,
                'title' => $course->title,
                'titleEn' => $course->title_en,
                'summary' => $course->summary,
                'summaryEn' => $course->summary_en,
                'level' => $course->level,
                'durationMinutes' => $course->duration_minutes,
                'lessonCount' => $course->lessons_count ?? null,
                'position' => (int) ($course->pivot->position ?: $index + 1),
                'prerequisiteCourseId' => $course->pivot->prerequisite_course_id,
            ]),
        ];
    }

    private function serializeAcademicPath(AcademicGrade $grade, Collection|EloquentCollection $courses): array
    {
        return [
            'id' => $grade->code,
            'type' => 'learning-path',
            'name' => 'مسار '.$grade->name_ar,
            'nameEn' => $grade->name_en.' Path',
            'description' => 'دورات '.$grade->name_ar.' مرتبة حسب الفصل والمستوى.',
            'descriptionEn' => $grade->name_en.' courses ordered by semester and level.',
            'courseCount' => $courses->count(),
            'durationMinutes' => (int) $courses->sum('duration_minutes'),
            'courses' => $courses->values()->map(fn (Course $course, int $index) => [
                'id' => $course->id,
                'title' => $course->title,
                'titleEn' => $course->title_en,
                'summary' => $course->summary,
                'summaryEn' => $course->summary_en,
                'level' => $course->level,
                'durationMinutes' => $course->duration_minutes,
                'lessonCount' => $course->lessons_count ?? null,
                'position' => $index + 1,
                'prerequisiteCourseId' => $index > 0 ? $courses->values()->get($index - 1)?->id : null,
            ]),
        ];
    }
}
