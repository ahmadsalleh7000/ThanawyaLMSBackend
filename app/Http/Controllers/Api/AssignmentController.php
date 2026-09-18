<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\TeacherCourseAssignment;
use App\Rules\AllowedUploadedFileExtension;
use App\Services\StudentContentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssignmentController extends Controller
{
    public function __construct(private readonly StudentContentAccessService $access) {}

    public function show(Request $request, Course $course, string $lesson, Assignment $assignment): JsonResponse
    {
        $assignment = $this->accessibleAssignment($request, $course, $lesson, $assignment);
        $submission = $assignment->submissions()->where('user_id', $request->user()->id)->first();

        return response()->json(['data' => $this->serialize($assignment, $submission)]);
    }

    public function submit(Request $request, Course $course, string $lesson, Assignment $assignment): JsonResponse
    {
        $assignment = $this->accessibleAssignment($request, $course, $lesson, $assignment);
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:20000', 'required_without:file'],
            'file' => ['nullable', 'file', 'max:10240', new AllowedUploadedFileExtension(['pdf', 'doc', 'docx', 'txt', 'zip']), 'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,application/zip'],
        ]);
        $submission = $assignment->submissions()->where('user_id', $request->user()->id)->first();
        $wasReturned = $submission?->status === 'returned';
        if ($submission?->file_path) {
            Storage::disk('local')->delete($submission->file_path);
        }
        $file = $request->file('file');
        $filePath = $file?->store('assignment-submissions/'.now()->format('Y/m'), 'local');
        $submittedAt = now();
        $submission = $assignment->submissions()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'body' => $data['body'] ?? null,
                'file_path' => $filePath,
                'file_original_name' => $file?->getClientOriginalName(),
                'file_mime' => $file?->getMimeType(),
                'file_size' => $file?->getSize(),
                'file_sha256' => $filePath ? hash_file('sha256', Storage::disk('local')->path($filePath)) : null,
                'status' => $wasReturned ? 'resubmitted' : ($assignment->due_at?->isPast() ? 'late' : 'submitted'),
                'submitted_at' => $submittedAt,
            ],
        );
        TeacherCourseAssignment::query()->active()->where('course_id', $course->id)->with('teacher')->get()->each(fn ($courseAssignment) => $courseAssignment->teacher?->userNotifications()->create(['type' => 'assignment_submission', 'title' => 'New assignment submission', 'title_ar' => 'تسليم مهمة جديد', 'message' => $assignment->title, 'message_ar' => $assignment->title, 'action_url' => '/teacher/courses/'.$course->id]));

        return response()->json(['data' => $this->serialize($assignment, $submission)]);
    }

    private function accessibleAssignment(Request $request, Course $course, string $lesson, Assignment $assignment): Assignment
    {
        $lessonModel = Lesson::query()->whereBelongsTo($course)->where('slug', $lesson)->firstOrFail();
        abort_unless($this->access->canAccessLesson($request->user(), $lessonModel), 403, 'An active subscription is required.');
        abort_unless($assignment->lesson_id === $lessonModel->id && $assignment->is_published, 404);

        return $assignment;
    }

    private function serialize(Assignment $assignment, ?AssignmentSubmission $submission): array
    {
        return [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'titleEn' => $assignment->title_en,
            'description' => $assignment->description,
            'descriptionEn' => $assignment->description_en,
            'dueDate' => $assignment->due_at?->toISOString(),
            'status' => $submission?->status ?? 'ready',
            'submission' => $submission ? ['id' => $submission->id, 'body' => $submission->body, 'fileName' => $submission->file_original_name, 'fileMime' => $submission->file_mime, 'fileSize' => $submission->file_size, 'submittedAt' => $submission->submitted_at?->toISOString()] : null,
        ];
    }
}
