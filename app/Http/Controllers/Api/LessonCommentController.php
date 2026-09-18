<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertLessonCommentRequest;
use App\Http\Resources\LessonCommentResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonComment;
use App\Models\TeacherCourseAssignment;
use App\Models\User;
use App\Services\StudentContentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LessonCommentController extends Controller
{
    public function __construct(private readonly StudentContentAccessService $access) {}

    public function index(Request $request, Course $course, string $lesson): JsonResponse
    {
        $lessonModel = $this->accessibleLesson($request, $course, $lesson);
        $comments = $lessonModel->comments()->whereNull('parent_id')
            ->with([
                'lesson:id,comments_enabled',
                'user:id,name,role',
                'replies' => fn ($query) => $query->with(['lesson:id,comments_enabled', 'user:id,name,role'])->oldest(),
            ])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => LessonCommentResource::collection($comments->items())->resolve($request),
            'meta' => [
                'currentPage' => $comments->currentPage(),
                'lastPage' => $comments->lastPage(),
                'perPage' => $comments->perPage(),
                'total' => $comments->total(),
                'commentCount' => $lessonModel->comments()->count(),
                'commentsEnabled' => (bool) $lessonModel->comments_enabled,
            ],
        ]);
    }

    public function store(UpsertLessonCommentRequest $request, Course $course, string $lesson): JsonResponse
    {
        $lessonModel = $this->accessibleLesson($request, $course, $lesson);
        $this->ensureEnabled($lessonModel);
        $comment = $lessonModel->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $request->validated('content'),
        ]);
        TeacherCourseAssignment::query()
            ->active()
            ->where('course_id', $course->id)
            ->with('teacher')
            ->get()
            ->each(fn (TeacherCourseAssignment $assignment) => $assignment->teacher?->userNotifications()->create([
                'type' => 'lesson_comment',
                'title' => 'New learner question',
                'title_ar' => 'سؤال جديد من طالب',
                'message' => $lessonModel->title,
                'message_ar' => $lessonModel->title,
                'action_url' => '/teacher/courses/'.$course->id,
                'data' => ['courseId' => $course->id, 'lessonId' => $lessonModel->id, 'commentId' => $comment->id],
            ]));

        return response()->json([
            'data' => new LessonCommentResource($this->loadComment($comment)),
        ], 201);
    }

    public function reply(UpsertLessonCommentRequest $request, Course $course, string $lesson, LessonComment $comment): JsonResponse
    {
        $lessonModel = $this->accessibleLesson($request, $course, $lesson);
        $this->ensureComment($lessonModel, $comment);
        $this->ensureEnabled($lessonModel);
        abort_if($comment->parent_id !== null, 422, 'Replies can only be added to a top-level comment.');
        $reply = $lessonModel->comments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $comment->id,
            'content' => $request->validated('content'),
        ]);
        if ($comment->user_id !== $request->user()->id) {
            $comment->loadMissing('user');
            $comment->user?->userNotifications()->create([
                'type' => 'lesson_comment',
                'title' => 'Your question received a reply',
                'title_ar' => 'تم الرد على سؤالك',
                'message' => $lessonModel->title,
                'message_ar' => $lessonModel->title,
                'action_url' => '/course-player/'.$course->id.'?lesson='.$lessonModel->slug,
                'data' => ['courseId' => $course->id, 'lessonId' => $lessonModel->id, 'commentId' => $comment->id],
            ]);
        }

        return response()->json([
            'data' => new LessonCommentResource($this->loadComment($reply)),
        ], 201);
    }

    public function update(UpsertLessonCommentRequest $request, Course $course, string $lesson, LessonComment $comment): JsonResponse
    {
        $lessonModel = $this->accessibleLesson($request, $course, $lesson);
        $this->ensureComment($lessonModel, $comment);
        Gate::authorize('update', $comment);
        $comment->update(['content' => $request->validated('content'), 'edited_at' => now()]);

        return response()->json([
            'data' => new LessonCommentResource($this->loadComment($comment)),
        ]);
    }

    public function destroy(Request $request, Course $course, string $lesson, LessonComment $comment): JsonResponse
    {
        $lessonModel = $this->accessibleLesson($request, $course, $lesson);
        $this->ensureComment($lessonModel, $comment);
        Gate::authorize('delete', $comment);
        DB::transaction(function () use ($comment): void {
            $comment->replies()->delete();
            $comment->delete();
        });

        return response()->json(status: 204);
    }

    private function accessibleLesson(Request $request, Course $course, string $lesson): Lesson
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [User::ROLE_STUDENT, User::ROLE_ADMIN], true), 403);
        $lessonModel = Lesson::whereBelongsTo($course)->where('slug', $lesson)->firstOrFail();
        abort_unless($this->access->canAccessLesson($user, $lessonModel), 403, 'Lesson access is required to join this discussion.');

        return $lessonModel;
    }

    private function ensureComment(Lesson $lesson, LessonComment $comment): void
    {
        abort_unless($comment->lesson_id === $lesson->id, 404);
    }

    private function ensureEnabled(Lesson $lesson): void
    {
        abort_unless($lesson->comments_enabled, 409, 'Comments are disabled for this lesson.');
    }

    private function loadComment(LessonComment $comment): LessonComment
    {
        return $comment->load(['lesson:id,comments_enabled', 'user:id,name,role']);
    }
}
