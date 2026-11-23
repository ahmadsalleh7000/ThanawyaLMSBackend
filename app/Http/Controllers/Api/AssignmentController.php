<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\TeacherCourseAssignment;
use App\Models\User;
use App\Rules\AllowedUploadedFileExtension;
use App\Services\AssignmentWorkflowService;
use App\Services\StudentContentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssignmentController extends Controller
{
    public function __construct(private readonly StudentContentAccessService $access, private readonly AssignmentWorkflowService $workflow) {}

    public function show(Request $request, Course $course, string $lesson, Assignment $assignment): JsonResponse
    {
        $assignment = $this->accessibleAssignment($request, $course, $lesson, $assignment);
        $submission = $assignment->submissions()->where('user_id', $request->user()->id)->first();

        return response()->json(['data' => $this->workflow->serialize($assignment, $submission)]);
    }

    public function submit(Request $request, Course $course, string $lesson, Assignment $assignment): JsonResponse
    {
        abort_unless($request->user()->role === User::ROLE_STUDENT, 403);
        $assignment = $this->accessibleAssignment($request, $course, $lesson, $assignment);
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:20000'],
            'file' => ['nullable', 'file', 'max:10240', new AllowedUploadedFileExtension(['pdf', 'doc', 'docx', 'txt', 'zip']), 'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,application/zip'],
        ]);
        $submission = $this->workflow->submit($request, $assignment, $data);
        TeacherCourseAssignment::query()->active()->where('course_id', $course->id)->with('teacher')->get()->each(fn ($courseAssignment) => $courseAssignment->teacher?->userNotifications()->create(['type' => 'assignment_submission', 'title' => 'New assignment submission', 'title_ar' => 'تسليم مهمة جديد', 'message' => $assignment->title, 'message_ar' => $assignment->title, 'action_url' => '/teacher/courses/'.$course->id]));

        return response()->json(['data' => $this->workflow->serialize($assignment, $submission)]);
    }

    private function accessibleAssignment(Request $request, Course $course, string $lesson, Assignment $assignment): Assignment
    {
        $lessonModel = Lesson::query()->whereBelongsTo($course)->where('slug', $lesson)->firstOrFail();
        abort_unless($this->access->canAccessLesson($request->user(), $lessonModel), 403, __('An active subscription is required.'));
        abort_unless($assignment->lesson_id === $lessonModel->id && $assignment->is_published, 404);

        return $assignment;
    }

    public function file(Request $request, Course $course, string $lesson, Assignment $assignment): BinaryFileResponse
    {
        $assignment = $this->accessibleAssignment($request, $course, $lesson, $assignment);
        $submission = $assignment->submissions()->where('user_id', $request->user()->id)->firstOrFail();

        return $this->workflow->download($submission);
    }
}
