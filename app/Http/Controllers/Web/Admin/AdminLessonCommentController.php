<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonComment;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminLessonCommentController extends Controller
{
    public function reply(Request $request, Course $course, Lesson $lesson, LessonComment $comment, AuditService $audit): RedirectResponse
    {
        $this->ensureContext($course, $lesson, $comment);
        abort_unless($lesson->comments_enabled, 409, 'Comments are disabled for this lesson.');
        abort_if($comment->parent_id, 422, 'Replies can only be added to a top-level comment.');
        $request->merge(['content' => trim((string) $request->input('content'))]);
        $data = $request->validate(['content' => ['required', 'string', 'min:2', 'max:2000']]);
        $reply = $lesson->comments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $comment->id,
            'content' => $data['content'],
        ]);
        if ($comment->user_id !== $request->user()->id) {
            $comment->loadMissing('user');
            $comment->user?->userNotifications()->create([
                'type' => 'lesson_comment',
                'title' => 'Your question received a reply',
                'title_ar' => 'تم الرد على سؤالك',
                'message' => $lesson->title,
                'message_ar' => $lesson->title,
                'action_url' => '/course-player/'.$course->id.'?lesson='.$lesson->slug,
                'data' => ['courseId' => $course->id, 'lessonId' => $lesson->id, 'commentId' => $comment->id],
            ]);
        }
        $audit->record($request, 'lesson_comment.replied', $reply);

        return back()->with('success', 'تم نشر رد فريق المنصة.');
    }

    public function destroy(Request $request, Course $course, Lesson $lesson, LessonComment $comment, AuditService $audit): RedirectResponse
    {
        $this->ensureContext($course, $lesson, $comment);
        $before = $comment->attributesToArray();
        DB::transaction(function () use ($comment): void {
            $comment->replies()->delete();
            $comment->delete();
        });
        $audit->record($request, 'lesson_comment.moderated', $comment, $before);

        return back()->with('success', 'تم حذف التعليق من المناقشة.');
    }

    private function ensureContext(Course $course, Lesson $lesson, LessonComment $comment): void
    {
        abort_unless($lesson->course_id === $course->id && $comment->lesson_id === $lesson->id, 404);
    }
}
