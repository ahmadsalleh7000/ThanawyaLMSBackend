<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertLessonCommentRequest;
use App\Http\Resources\LessonCommentResource;
use App\Models\Course;
use App\Models\LessonComment;
use App\Models\User;
use App\Services\StudentContentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseDiscussionController extends Controller
{
    public function __construct(private readonly StudentContentAccessService $access) {}

    public function index(Request $request, Course $course): JsonResponse
    {
        $this->authorizeCourse($request, $course);
        $comments = LessonComment::where('course_id', $course->id)->whereNull('parent_id')
            ->with(['user:id,name,role', 'replies.user:id,name,role'])->latest()->paginate(20);

        return response()->json([
            'data' => LessonCommentResource::collection($comments->items())->resolve($request),
            'meta' => ['currentPage' => $comments->currentPage(), 'lastPage' => $comments->lastPage(), 'total' => $comments->total(), 'commentCount' => LessonComment::where('course_id', $course->id)->count(), 'commentsEnabled' => true],
        ]);
    }

    public function store(UpsertLessonCommentRequest $request, Course $course): JsonResponse
    {
        $this->authorizeCourse($request, $course);
        $comment = LessonComment::create(['course_id' => $course->id, 'user_id' => $request->user()->id, 'content' => $request->validated('content')]);

        return response()->json(['data' => new LessonCommentResource($comment->load('user:id,name,role'))], 201);
    }

    public function reply(UpsertLessonCommentRequest $request, Course $course, LessonComment $comment): JsonResponse
    {
        $this->authorizeCourse($request, $course);
        abort_unless($comment->course_id === $course->id && $comment->parent_id === null, 404);
        $reply = LessonComment::create(['course_id' => $course->id, 'parent_id' => $comment->id, 'user_id' => $request->user()->id, 'content' => $request->validated('content')]);

        return response()->json(['data' => new LessonCommentResource($reply->load('user:id,name,role'))], 201);
    }

    private function authorizeCourse(Request $request, Course $course): void
    {
        abort_unless(in_array($request->user()->role, [User::ROLE_STUDENT, User::ROLE_ADMIN], true), 403);
        abort_unless($this->access->canAccessCourse($request->user(), $course), 403, 'Course access is required to join this discussion.');
    }
}
