<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertLessonRequest;
use App\Models\Course;
use App\Models\Lesson;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminLessonController extends Controller
{
    public function store(UpsertLessonRequest $request, Course $course, AuditService $audit): JsonResponse
    {
        $this->ensureEditable($course);
        $lesson = $course->lessons()->create($request->validated());
        $audit->record($request, 'lesson.created', $lesson);

        return response()->json(['data' => $lesson], 201);
    }

    public function update(UpsertLessonRequest $request, Course $course, Lesson $lesson, AuditService $audit): JsonResponse
    {
        $this->ensureLesson($course, $lesson);
        $this->ensureEditable($course);
        $before = $lesson->attributesToArray();
        $lesson->update($request->validated());
        $audit->record($request, 'lesson.updated', $lesson, $before);

        return response()->json(['data' => $lesson]);
    }

    public function destroy(Request $request, Course $course, Lesson $lesson, AuditService $audit): JsonResponse
    {
        $this->ensureLesson($course, $lesson);
        abort_if($course->is_published, 409, 'Unpublish the course before deleting lessons.');
        $before = $lesson->attributesToArray();
        $audit->record($request, 'lesson.deleted', $lesson, $before);
        $lesson->delete();

        return response()->json(status: 204);
    }

    public function reorder(Request $request, Course $course, AuditService $audit): JsonResponse
    {
        $this->ensureEditable($course);
        $validated = $request->validate(['lesson_ids' => ['required', 'array'], 'lesson_ids.*' => ['integer', 'distinct']]);
        $lessons = $course->lessons()->whereIn('id', $validated['lesson_ids'])->get();
        abort_unless($lessons->count() === $course->lessons()->count() && $lessons->count() === count($validated['lesson_ids']), 422, 'Provide every course lesson exactly once.');
        $before = $lessons->map->attributesToArray()->all();
        DB::transaction(function () use ($course, $validated) {
            $course->lessons()->update(['position' => DB::raw('position + 100000')]);
            foreach ($validated['lesson_ids'] as $index => $lessonId) {
                $course->lessons()->whereKey($lessonId)->update(['position' => $index + 1]);
            }
        });
        $audit->record($request, 'lessons.reordered', $course, ['lessons' => $before]);

        return response()->json(['data' => $course->lessons()->get()]);
    }

    private function ensureLesson(Course $course, Lesson $lesson): void
    {
        abort_unless($lesson->course_id === $course->id, 404);
    }

    private function ensureEditable(Course $course): void
    {
        abort_if($course->archived_at, 409, 'Archived courses cannot be edited.');
    }
}
