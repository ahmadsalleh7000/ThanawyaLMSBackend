<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\User;
use App\Services\StudentContentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentAssignmentHubController extends Controller
{
    public function index(Request $request, StudentContentAccessService $access): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->role === User::ROLE_STUDENT, 403);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'course_id' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['ready', 'overdue', 'submitted', 'late', 'returned', 'resubmitted', 'graded'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $own = fn ($q) => $q->where('user_id', $student->id);
        $courseIds = $access->courseIdsFor($student);
        $lessonAccess = [];
        $all = Assignment::select('id', 'lesson_id', 'title', 'title_en', 'due_at', 'is_published')->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('is_published', true)->whereHas('lesson', fn ($q) => $q->where('status', 'published')->whereIn('course_id', $courseIds)))
            ->orWhereHas('submissions', $own))
            ->with([
                'lesson:id,course_id,unit_id,slug,title,title_en,status,available_from,available_until',
                'lesson.course:id,title,title_en,scope,academic_subject_id,is_published,archived_at',
                'lesson.unit:id,status,available_from,available_until',
                'submissions' => fn ($q) => $own($q)->select('id', 'assignment_id', 'user_id', 'status', 'score', 'feedback', 'submitted_at', 'file_original_name'),
            ])->get()
            ->map(function ($assignment) use ($student, $access, &$lessonAccess) {
                $lesson = $assignment->lesson;
                $submission = $assignment->submissions->first();
                $lessonAccess[$lesson->id] ??= $access->canAccessLesson($student, $lesson);
                $canOpen = $assignment->is_published && $lessonAccess[$lesson->id];
                $status = $submission?->status ?? ($assignment->due_at?->isPast() ? 'overdue' : 'ready');

                return [
                    'id' => $assignment->id, 'title' => $assignment->title, 'titleEn' => $assignment->title_en,
                    'course' => ['id' => $lesson->course_id, 'title' => $lesson->course->title, 'titleEn' => $lesson->course->title_en],
                    'lesson' => ['id' => $lesson->slug, 'title' => $lesson->title, 'titleEn' => $lesson->title_en],
                    'dueAt' => $assignment->due_at?->toISOString(), 'status' => $status,
                    'submittedAt' => $submission?->submitted_at?->toISOString(),
                    'score' => $submission?->score, 'feedback' => $submission?->feedback,
                    'fileName' => $submission?->file_original_name,
                    'canOpen' => $canOpen, 'canSubmit' => $canOpen && $status !== 'graded',
                    'url' => '/course-player/'.rawurlencode($lesson->course_id).'?'.http_build_query(['lesson' => $lesson->slug, 'tab' => 'assignments', 'assignment' => $assignment->id]),
                ];
            })->sortBy(fn ($item) => [! $item['canSubmit'], ! in_array($item['status'], ['returned', 'overdue', 'ready'], true), $item['dueAt'] ?? '9999', $item['id']])->values();
        $scores = $all->where('status', 'graded')->pluck('score')->filter(fn ($score) => $score !== null);
        $rows = $all->filter(function ($item) use ($filters) {
            if (! empty($filters['course_id']) && $item['course']['id'] !== $filters['course_id']) {
                return false;
            }
            if (! empty($filters['status']) && $item['status'] !== $filters['status']) {
                return false;
            }
            $search = mb_strtolower(trim($filters['search'] ?? ''));

            return $search === '' || str_contains(mb_strtolower(implode(' ', [$item['title'], $item['titleEn'], $item['course']['title'], $item['course']['titleEn'], $item['lesson']['title'], $item['lesson']['titleEn']])), $search);
        })->values();
        $page = $request->integer('page', 1);

        return response()->json([
            'data' => $rows->forPage($page, 12)->values(),
            'meta' => [
                'currentPage' => $page, 'lastPage' => max(1, (int) ceil($rows->count() / 12)), 'total' => $rows->count(),
                'courses' => $all->pluck('course')->unique('id')->values(),
                'summary' => [
                    'toDo' => $all->where('canSubmit', true)->whereIn('status', ['ready', 'overdue', 'returned'])->count(),
                    'awaitingReview' => $all->whereIn('status', ['submitted', 'late', 'resubmitted'])->count(),
                    'graded' => $all->where('status', 'graded')->count(),
                    'averageScore' => $scores->isEmpty() ? null : round($scores->avg(), 1),
                ],
            ],
        ])->header('Cache-Control', 'private, no-store');
    }
}
