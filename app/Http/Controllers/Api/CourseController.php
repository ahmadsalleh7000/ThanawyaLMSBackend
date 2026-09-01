<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Services\StudentContentAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseController extends Controller
{
    public function __construct(private readonly StudentContentAccessService $access) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user('sanctum');
        $courses = ($user?->role === 'student'
            ? $this->access->visibleCoursesQuery($user)
            : Course::available())
            ->with(['lessons.unit', 'lessons.sections', 'lessons.mediaAssets.libraryItem', 'lessons.trainings' => fn ($query) => $query->with(['quiz.questions'])->withCount('questions')])->get();
        $accessibleCourseIds = $user ? $this->access->courseIdsFor($user) : collect();
        $courses->each(function (Course $course) use ($user, $accessibleCourseIds): void {
            $canAccess = $accessibleCourseIds->contains($course->id);
            $this->filterStudentLessons($course, $user, $canAccess);
            $this->markLessonAccess($course, $user, $canAccess);
        });

        return CourseResource::collection($courses);
    }

    public function show(Request $request, Course $course): CourseResource
    {
        $user = $request->user('sanctum');
        abort_unless($course->is_published && ! $course->archived_at, 404);
        if ($user?->role === 'student') {
            abort_unless($this->access->canAccessCourse($user, $course), 403, 'This course is not included in your active learning access.');
        }
        $course->load(['lessons.unit', 'lessons.sections', 'lessons.mediaAssets.libraryItem', 'lessons.trainings' => fn ($query) => $query->with(['quiz.questions'])->withCount('questions')]);
        $canAccess = $user && $this->access->canAccessCourse($user, $course);
        $this->filterStudentLessons($course, $user, $canAccess);
        $this->markLessonAccess($course, $user, $canAccess);

        return new CourseResource($course);
    }

    private function filterStudentLessons(Course $course, $user, bool $canAccess): void
    {
        if ($user?->role !== 'student') {
            return;
        }

        $course->setRelation('lessons', $course->lessons
            ->filter(fn (Lesson $lesson) => $lesson->is_preview || ($canAccess && $this->access->canAccessLesson($user, $lesson)))
            ->values());
    }

    private function markLessonAccess(Course $course, $user, bool $canAccess): void
    {
        $course->lessons->each(
            fn ($lesson) => $lesson->setAttribute('content_access_granted', $canAccess && $user && $this->access->canAccessLesson($user, $lesson)),
        );
    }
}
