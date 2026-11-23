<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudentLearningResultsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === User::ROLE_STUDENT, 403);
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(['exam', 'quiz', 'assignment'])],
            'result' => ['nullable', Rule::in(['passed', 'needs_review', 'awaiting_review', 'returned', 'graded'])],
            'course_id' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:150'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $studentId = $request->user()->id;
        $exams = DB::table('assessment_attempts as attempts')
            ->join('assessments as targets', 'targets.id', '=', 'attempts.assessment_id')
            ->join('courses', 'courses.id', '=', 'targets.course_id')
            ->where('attempts.user_id', $studentId)->whereNotNull('attempts.submitted_at')
            ->selectRaw("attempts.id, 'exam' as kind, targets.id as target_id, attempts.submitted_at, COALESCE(attempts.manual_score, attempts.score) as score, CASE WHEN attempts.passed = 1 THEN 'passed' ELSE 'needs_review' END as result, attempts.manual_feedback as feedback, courses.id as course_id, courses.title as course_title, courses.title_en as course_title_en, courses.title as title, courses.title_en as title_en, NULL as lesson_title, NULL as lesson_title_en");
        $quizzes = DB::table('training_attempts as attempts')
            ->join('trainings as targets', 'targets.id', '=', 'attempts.training_id')
            ->join('lessons', 'lessons.id', '=', 'targets.trainable_id')
            ->join('courses', 'courses.id', '=', 'lessons.course_id')
            ->where('targets.trainable_type', Lesson::class)
            ->where('attempts.student_id', $studentId)->whereNotNull('attempts.submitted_at')
            ->selectRaw("attempts.id, 'quiz' as kind, targets.id as target_id, attempts.submitted_at, attempts.score, CASE WHEN attempts.status = 'passed' THEN 'passed' ELSE 'needs_review' END as result, NULL as feedback, courses.id as course_id, courses.title as course_title, courses.title_en as course_title_en, targets.title_ar as title, targets.title_en as title_en, lessons.title as lesson_title, lessons.title_en as lesson_title_en");
        $assignments = DB::table('assignment_submissions as attempts')
            ->join('assignments as targets', 'targets.id', '=', 'attempts.assignment_id')
            ->join('lessons', 'lessons.id', '=', 'targets.lesson_id')
            ->join('courses', 'courses.id', '=', 'lessons.course_id')
            ->where('attempts.user_id', $studentId)->whereNotNull('attempts.submitted_at')
            ->selectRaw("attempts.id, 'assignment' as kind, targets.id as target_id, attempts.submitted_at, CASE WHEN attempts.status = 'graded' THEN attempts.score ELSE NULL END as score, CASE WHEN attempts.status = 'graded' THEN 'graded' WHEN attempts.status = 'returned' THEN 'returned' ELSE 'awaiting_review' END as result, attempts.feedback, courses.id as course_id, courses.title as course_title, courses.title_en as course_title_en, targets.title, targets.title_en, lessons.title as lesson_title, lessons.title_en as lesson_title_en");
        $base = DB::query()->fromSub($exams->unionAll($quizzes)->unionAll($assignments), 'results');
        $courses = (clone $base)->select('course_id', 'course_title', 'course_title_en')->distinct()->orderBy('course_title')->get();
        $query = (clone $base)
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('kind', $type))
            ->when($filters['result'] ?? null, fn ($q, $result) => $q->where('result', $result))
            ->when($filters['course_id'] ?? null, fn ($q, $courseId) => $q->where('course_id', $courseId));
        if ($search = trim($filters['search'] ?? '')) {
            $query->where(function ($q) use ($search) {
                foreach (['title', 'title_en', 'course_title', 'course_title_en', 'lesson_title', 'lesson_title_en'] as $column) {
                    $q->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }
        $average = (clone $query)->avg('score');
        $summary = [
            'total' => (clone $query)->count(),
            'passed' => (clone $query)->where('result', 'passed')->count(),
            'awaitingReview' => (clone $query)->where('result', 'awaiting_review')->count(),
            'averageScore' => $average === null ? null : round((float) $average, 1),
        ];
        $records = $query->orderByDesc('submitted_at')->orderBy('kind')->orderByDesc('id')->paginate(12);

        return response()->json([
            'data' => $records->getCollection()->map(fn ($row) => [
                'id' => $row->kind.'-'.$row->id, 'type' => $row->kind,
                'title' => $row->title, 'titleEn' => $row->title_en,
                'course' => ['id' => $row->course_id, 'title' => $row->course_title, 'titleEn' => $row->course_title_en],
                'lesson' => $row->lesson_title ? ['title' => $row->lesson_title, 'titleEn' => $row->lesson_title_en] : null,
                'score' => $row->score === null ? null : (float) $row->score,
                'result' => $row->result, 'feedback' => $row->feedback,
                'submittedAt' => Carbon::parse($row->submitted_at)->toISOString(),
                'url' => $row->kind !== 'assignment' ? '/assessment-review/'.$row->kind.'/'.$row->target_id.'/'.$row->id : '/my-assessments/assignments?'.http_build_query([
                    'course_id' => $row->course_id,
                    ...($row->kind === 'assignment' ? ['search' => $row->title] : ['type' => $row->kind]),
                ]),
            ])->values(),
            'meta' => [
                'currentPage' => $records->currentPage(), 'lastPage' => $records->lastPage(), 'total' => $records->total(),
                'summary' => $summary,
                'courses' => $courses->map(fn ($course) => ['id' => $course->course_id, 'title' => $course->course_title, 'titleEn' => $course->course_title_en]),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }
}
