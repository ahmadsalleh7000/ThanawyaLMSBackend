<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Lesson;
use App\Models\Training;
use App\Models\User;
use App\Services\ExamDeliveryService;
use App\Services\ExamSubmissionService;
use App\Services\StudentAssessmentHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class StudentAssessmentHubController extends Controller
{
    public function index(Request $request, StudentAssessmentHubService $hub): JsonResponse
    {
        abort_unless($request->user()->role === User::ROLE_STUDENT, 403);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'type' => ['nullable', Rule::in(['exam', 'quiz'])],
            'result' => ['nullable', Rule::in(['not_started', 'in_progress', 'passed', 'needs_review'])],
            'availability' => ['nullable', Rule::in(['available', 'upcoming', 'closed'])],
            'course_id' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $all = $hub->for($request->user());
        $scores = $all->pluck('bestScore')->filter(fn ($score) => $score !== null);
        $rows = $all->filter(function ($item) use ($filters) {
            if (! empty($filters['type']) && $item['type'] !== $filters['type']) {
                return false;
            }
            if (! empty($filters['result']) && $item['result'] !== $filters['result']) {
                return false;
            }
            if (! empty($filters['course_id']) && $item['course']['id'] !== $filters['course_id']) {
                return false;
            }
            if (! empty($filters['availability']) && match ($filters['availability']) {
                'available' => ! $item['canStart'],
                'upcoming' => $item['blockedReason'] !== 'upcoming',
                'closed' => $item['blockedReason'] !== 'closed',
            }) {
                return false;
            }
            $search = mb_strtolower(trim($filters['search'] ?? ''));

            return $search === '' || str_contains(mb_strtolower(implode(' ', [$item['title'], $item['titleEn'], $item['course']['title'], $item['course']['titleEn'], $item['lesson']['title'] ?? '', $item['lesson']['titleEn'] ?? ''])), $search);
        })->values();
        $page = $request->integer('page', 1);
        $paginator = new LengthAwarePaginator($rows->forPage($page, 12)->values(), $rows->count(), 12, $page);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'currentPage' => $page, 'lastPage' => $paginator->lastPage(), 'total' => $paginator->total(),
                'summary' => ['total' => $all->count(), 'available' => $all->where('canStart', true)->count(), 'passed' => $all->where('result', 'passed')->count(), 'attempts' => $all->sum('attemptsCount'), 'averageBestScore' => $scores->isEmpty() ? null : round($scores->avg(), 1)],
                'courses' => $all->pluck('course')->unique('id')->values(),
            ],
        ]);
    }

    public function review(Request $request, string $type, int $id, int $attempt): JsonResponse
    {
        abort_unless($request->user()->role === User::ROLE_STUDENT, 403);
        $exam = match ($type) {
            'exam' => Assessment::findOrFail($id), 'quiz' => Training::findOrFail($id), default => abort(404),
        };
        $record = $exam->attempts()->where($type === 'exam' ? 'user_id' : 'student_id', $request->user()->id)
            ->whereNotNull('submitted_at')->findOrFail($attempt);
        $course = $type === 'exam' ? $exam->course : ($exam->trainable instanceof Lesson ? $exam->trainable->course : null);
        abort_unless($course, 404);
        $questions = app(ExamDeliveryService::class)->questions($exam, $request->user()->id, $record->question_snapshot);
        $result = app(ExamSubmissionService::class)->result($exam, $record);

        return response()->json(['data' => [
            'course' => ['id' => $course->id, 'title' => $course->title, 'titleEn' => $course->title_en],
            'submittedAt' => $record->submitted_at->toISOString(), 'feedback' => $record->manual_feedback,
            'result' => $result,
            'questions' => $result['reviewAllowed'] ? $questions->map(fn ($question) => [
                'id' => $question->id, 'type' => $question->type, 'prompt' => $question->prompt, 'promptAr' => $question->prompt_ar,
                'options' => $question->options, 'optionsAr' => $question->options_ar,
            ])->values() : [],
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function attempts(Request $request, string $type, int $id): JsonResponse
    {
        abort_unless($request->user()->role === User::ROLE_STUDENT, 403);
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $exam = match ($type) {
            'exam' => Assessment::findOrFail($id), 'quiz' => Training::findOrFail($id), default => abort(404)
        };
        $query = $exam->attempts()->where($type === 'exam' ? 'user_id' : 'student_id', $request->user()->id)->whereNotNull('submitted_at');
        abort_unless((clone $query)->exists(), 404);
        $attempts = $query->latest('submitted_at')->orderByDesc('id')->paginate(10);

        return response()->json([
            'data' => $attempts->getCollection()->map(fn ($attempt) => ['id' => $attempt->id, 'reviewUrl' => '/assessment-review/'.$type.'/'.$id.'/'.$attempt->id, 'score' => (float) ($type === 'exam' ? $attempt->effective_score : $attempt->score), 'passed' => $type === 'exam' ? $attempt->passed : $attempt->status === 'passed', 'submittedAt' => $attempt->submitted_at->toISOString()]),
            'meta' => ['currentPage' => $attempts->currentPage(), 'lastPage' => $attempts->lastPage(), 'total' => $attempts->total()],
        ]);
    }
}
