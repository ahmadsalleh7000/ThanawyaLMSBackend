<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonPrerequisite;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminLessonPrerequisiteController extends Controller
{
    public function store(Request $request, Lesson $lesson, AuditService $audit): JsonResponse
    {
        $data = $request->validate(['prerequisite_lesson_id' => ['required', 'exists:lessons,id']]);
        $prerequisite = Lesson::findOrFail($data['prerequisite_lesson_id']);
        abort_unless($prerequisite->course_id === $lesson->course_id, 422, 'Lessons must belong to the same course.');
        abort_if($prerequisite->id === $lesson->id, 422, 'A lesson cannot require itself.');
        $rule = LessonPrerequisite::firstOrCreate(['lesson_id' => $lesson->id, 'prerequisite_lesson_id' => $prerequisite->id]);
        $audit->record($request, 'lesson.prerequisite.created', $rule);

        return response()->json(['data' => ['lessonId' => $rule->lesson_id, 'prerequisiteLessonId' => $rule->prerequisite_lesson_id]], 201);
    }

    public function destroy(Request $request, LessonPrerequisite $prerequisite, AuditService $audit): JsonResponse
    {
        $before = $prerequisite->attributesToArray();
        $prerequisite->delete();
        $audit->record($request, 'lesson.prerequisite.deleted', $prerequisite, $before);

        return response()->json(['data' => ['deleted' => true]]);
    }
}
