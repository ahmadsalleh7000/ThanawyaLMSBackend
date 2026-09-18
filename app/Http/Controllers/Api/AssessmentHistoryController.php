<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssessmentHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === User::ROLE_STUDENT, 403);

        $filters = $request->validate([
            'result' => ['nullable', Rule::in(['passed', 'needs_review'])],
            'course_id' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = AssessmentAttempt::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('assessment.course')
            ->when(($filters['result'] ?? null) === 'passed', fn ($builder) => $builder->where('passed', true))
            ->when(($filters['result'] ?? null) === 'needs_review', fn ($builder) => $builder->where('passed', false))
            ->when($filters['course_id'] ?? null, fn ($builder, $courseId) => $builder->whereHas('assessment', fn ($assessment) => $assessment->where('course_id', $courseId)));

        $summaryQuery = clone $query;
        $summary = [
            'attempts' => (clone $summaryQuery)->count(),
            'passed' => (clone $summaryQuery)->where('passed', true)->count(),
            'averageScore' => round((float) ((clone $summaryQuery)->avg('score') ?? 0)),
            'bestScore' => (int) ((clone $summaryQuery)->max('score') ?? 0),
        ];
        $attempts = $query->with('assessment.course:id,title,title_en,image_url')
            ->latest('submitted_at')
            ->paginate(12)
            ->withQueryString();

        return response()->json([
            'data' => $attempts->getCollection()->map(fn (AssessmentAttempt $attempt) => [
                'id' => $attempt->id,
                'course' => [
                    'id' => $attempt->assessment->course->id,
                    'titleAr' => $attempt->assessment->course->title,
                    'titleEn' => $attempt->assessment->course->title_en,
                    'imageUrl' => $attempt->assessment->course->image_url,
                ],
                'score' => $attempt->score,
                'passingScore' => $attempt->assessment->passing_score,
                'passed' => $attempt->passed,
                'submittedAt' => $attempt->submitted_at?->toISOString(),
            ])->values(),
            'meta' => [
                'currentPage' => $attempts->currentPage(),
                'lastPage' => $attempts->lastPage(),
                'perPage' => $attempts->perPage(),
                'total' => $attempts->total(),
                'summary' => $summary,
            ],
        ]);
    }
}
