<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonFeedback;
use App\Services\StudentContentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonFeedbackController extends Controller
{
    public function show(Request $request, Course $course, string $lesson): JsonResponse
    {
        $lessonModel = $this->accessibleLesson($request, $course, $lesson);
        $feedback = LessonFeedback::where(['lesson_id' => $lessonModel->id, 'user_id' => $request->user()->id])->first();

        return response()->json(['data' => $feedback ? ['rating' => $feedback->rating, 'comment' => $feedback->comment] : null]);
    }

    public function store(Request $request, Course $course, string $lesson): JsonResponse
    {
        $lessonModel = $this->accessibleLesson($request, $course, $lesson);
        $data = $request->validate(['rating' => ['required', 'integer', 'min:1', 'max:5'], 'comment' => ['nullable', 'string', 'max:1000']]);
        $feedback = LessonFeedback::updateOrCreate(['lesson_id' => $lessonModel->id, 'user_id' => $request->user()->id], $data);

        return response()->json(['data' => ['rating' => $feedback->rating, 'comment' => $feedback->comment]]);
    }

    private function accessibleLesson(Request $request, Course $course, string $lesson): Lesson
    {
        $model = Lesson::whereBelongsTo($course)->where('slug', $lesson)->firstOrFail();
        abort_unless(app(StudentContentAccessService::class)->canAccessLesson($request->user(), $model), 403);

        return $model;
    }
}
