<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Enums\CourseScope;
use App\Models\AcademicSubject;
use App\Services\AuditService;
use App\Services\CourseLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminCourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $courses = Course::query()->withCount(['lessons', 'enrollments'])->latest()->get();

        return response()->json(['data' => $courses->map(fn (Course $course) => $this->serialize($course))]);
    }

    public function show(Course $course): JsonResponse
    {
        return response()->json(['data' => $this->serialize($course->load('lessons.mediaAssets'))]);
    }

    public function store(UpsertCourseRequest $request, AuditService $audit): JsonResponse
    {
        $course = Course::create($this->courseData($request) + ['is_published' => false]);
        $audit->record($request, 'course.created', $course);

        return response()->json(['data' => $this->serialize($course)], 201);
    }

    public function update(UpsertCourseRequest $request, Course $course, AuditService $audit): JsonResponse
    {
        abort_if($course->archived_at, 409, 'Archived courses must be restored before editing.');
        $scope = $request->validated('scope');
        if ($scope && $scope !== ($course->scope?->value ?? CourseScope::Academic->value) && ($course->is_published || $course->enrollments()->exists())) {
            throw ValidationException::withMessages(['scope' => ['Unpublish the course and resolve existing enrollments before changing its audience.']]);
        }
        $before = $course->attributesToArray();
        $course->update($this->courseData($request));
        $audit->record($request, 'course.updated', $course, $before);

        return response()->json(['data' => $this->serialize($course)]);
    }

    public function publish(Request $request, Course $course, AuditService $audit, CourseLifecycleService $lifecycle): JsonResponse
    {
        return $this->transition($request, $course, 'publish', $audit, $lifecycle);
    }

    public function unpublish(Request $request, Course $course, AuditService $audit, CourseLifecycleService $lifecycle): JsonResponse
    {
        return $this->transition($request, $course, 'unpublish', $audit, $lifecycle);
    }

    public function archive(Request $request, Course $course, AuditService $audit, CourseLifecycleService $lifecycle): JsonResponse
    {
        return $this->transition($request, $course, 'archive', $audit, $lifecycle);
    }

    public function restore(Request $request, Course $course, AuditService $audit, CourseLifecycleService $lifecycle): JsonResponse
    {
        return $this->transition($request, $course, 'restore', $audit, $lifecycle);
    }

    private function transition(Request $request, Course $course, string $action, AuditService $audit, CourseLifecycleService $lifecycle): JsonResponse
    {
        $before = $course->attributesToArray();
        $lifecycle->transition($course, $action);
        $event = ['publish' => 'published', 'unpublish' => 'unpublished', 'archive' => 'archived', 'restore' => 'restored'][$action];
        $audit->record($request, "course.{$event}", $course, $before);

        return response()->json(['data' => $this->serialize($course)]);
    }

    private function serialize(Course $course): array
    {
        return array_merge((new CourseResource($course))->resolve(), [
            'isPublished' => $course->is_published,
            'archivedAt' => $course->archived_at?->toISOString(),
            'lessonCount' => $course->lessons_count ?? $course->lessons()->count(),
            'enrollmentCount' => $course->enrollments_count ?? $course->enrollments()->count(),
            'durationMinutes' => $course->duration_minutes,
        ]);
    }

    private function courseData(UpsertCourseRequest $request): array
    {
        $data = $request->validated();
        if (($data['scope'] ?? null) === CourseScope::General->value) {
            return array_merge($data, ['academic_subject_id' => null, 'grade' => null, 'semester' => null]);
        }
        if ($subject = AcademicSubject::with(['term.grade', 'subject'])->find($data['academic_subject_id'] ?? null)) {
            $data['grade'] = $subject->term->grade->code;
            $data['semester'] = $subject->term->code;
            $data['category'] = $subject->subject->name_en;
        }

        return $data;
    }
}
