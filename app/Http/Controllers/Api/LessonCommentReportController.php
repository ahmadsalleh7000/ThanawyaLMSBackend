<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonComment;
use App\Models\LessonCommentReport;
use App\Models\User;
use App\Services\AuditService;
use App\Services\StudentContentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LessonCommentReportController extends Controller
{
    public function __construct(private readonly StudentContentAccessService $access) {}

    public function store(Request $request, Course $course, string $lesson, LessonComment $comment): JsonResponse
    {
        $lessonModel = $this->accessibleLesson($request, $course, $lesson);
        abort_unless($comment->lesson_id === $lessonModel->id, 404);
        $data = $request->validate([
            'reason' => ['required', 'string', Rule::in(['harassment', 'inappropriate', 'spam', 'privacy', 'other'])],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);
        $report = LessonCommentReport::firstOrCreate(
            ['lesson_comment_id' => $comment->id, 'reporter_id' => $request->user()->id],
            ['reason' => $data['reason'], 'details' => $data['details'] ?? null, 'status' => 'pending'],
        );

        return response()->json(['data' => ['id' => $report->id, 'status' => $report->status]], $report->wasRecentlyCreated ? 201 : 200);
    }

    public function index(Request $request): JsonResponse
    {
        $reports = LessonCommentReport::query()->with([
            'comment.lesson:id,course_id,slug,title,title_en',
            'comment.user:id,name',
            'reporter:id,name,email',
        ])->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return response()->json(['data' => $reports->getCollection()->map(fn (LessonCommentReport $report) => [
            'id' => $report->id,
            'reason' => $report->reason,
            'details' => $report->details,
            'status' => $report->status,
            'createdAt' => $report->created_at?->toISOString(),
            'comment' => ['id' => $report->comment?->id, 'content' => $report->comment?->content, 'author' => $report->comment?->user?->only(['id', 'name'])],
            'lesson' => $report->comment?->lesson?->only(['id', 'course_id', 'slug', 'title', 'title_en']),
            'reporter' => $report->reporter?->only(['id', 'name', 'email']),
        ])->values(), 'meta' => ['currentPage' => $reports->currentPage(), 'lastPage' => $reports->lastPage(), 'total' => $reports->total()]]);
    }

    public function update(Request $request, LessonCommentReport $report, AuditService $audit): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['resolved', 'dismissed'])], 'hide_comment' => ['sometimes', 'boolean']]);
        $before = $report->attributesToArray();
        $report->update(['status' => $data['status'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        if (($data['hide_comment'] ?? false) && $data['status'] === 'resolved') {
            $report->comment?->delete();
        }
        $audit->record($request, 'lesson_comment_report.reviewed', $report, $before);

        return response()->json(['data' => ['id' => $report->id, 'status' => $report->status]]);
    }

    private function accessibleLesson(Request $request, Course $course, string $lesson): Lesson
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [User::ROLE_STUDENT, User::ROLE_ADMIN], true), 403);
        $lessonModel = Lesson::whereBelongsTo($course)->where('slug', $lesson)->firstOrFail();
        abort_unless($user->isAdmin() || $this->access->canAccessLesson($user, $lessonModel), 403);

        return $lessonModel;
    }
}
