<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuestionBankQuiz;
use App\Models\Training;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminExamHubController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'type' => ['nullable', Rule::in(['assessment', 'training', 'template'])],
            'status' => ['nullable', Rule::in(['published', 'draft', 'archived'])],
            'course_id' => ['nullable', 'string', 'exists:courses,id'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim($filters['search'] ?? '');
        $type = $filters['type'] ?? null;
        $status = $filters['status'] ?? null;
        $courseId = $filters['course_id'] ?? null;
        $assessments = Assessment::query()->select('id', 'updated_at')->selectRaw("'assessment' as kind")
            ->when($type && $type !== 'assessment', fn ($q) => $q->whereRaw('1 = 0'))
            ->when($search, fn ($q) => $q->whereHas('course', fn ($c) => $this->searchCourse($c, $search)))
            ->when($courseId, fn ($q) => $q->where('course_id', $courseId))
            ->when($status, fn ($q) => $status === 'archived' ? $q->whereRaw('1 = 0') : $q->where('is_published', $status === 'published'));
        $trainings = Training::query()->select('id', 'updated_at')->selectRaw("'training' as kind")
            ->when($type && $type !== 'training', fn ($q) => $q->whereRaw('1 = 0'))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('title_ar', 'like', '%'.$search.'%')->orWhere('title_en', 'like', '%'.$search.'%')->orWhereHasMorph('trainable', [Lesson::class, Unit::class, Course::class], function ($parent, $kind) use ($search) {
                if ($kind === Course::class) {
                    $this->searchCourse($parent, $search);
                } else {
                    $parent->whereHas('course', fn ($c) => $this->searchCourse($c, $search));
                }
            })))
            ->when($courseId, fn ($q) => $this->forCourse($q, $courseId))
            ->when($status, fn ($q) => $q->where('status', $status));
        $templates = QuestionBankQuiz::query()->select('id', 'updated_at')->selectRaw("'template' as kind")
            ->when($type && $type !== 'template', fn ($q) => $q->whereRaw('1 = 0'))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('name_ar', 'like', '%'.$search.'%')->orWhere('name_en', 'like', '%'.$search.'%')))
            ->when($courseId, fn ($q) => $q->whereHas('trainings', fn ($t) => $this->forCourse($t, $courseId)))
            ->when($status, fn ($q) => $status === 'archived' ? $q->whereRaw('1 = 0') : $q->where('is_active', $status === 'published'));
        $records = DB::query()->fromSub($assessments->toBase()->unionAll($trainings->toBase())->unionAll($templates->toBase()), 'exam_records')
            ->orderByDesc('updated_at')->orderBy('kind')->orderByDesc('id')->paginate(15)->withQueryString();
        $ids = fn ($kind) => $records->getCollection()->where('kind', $kind)->pluck('id');
        $assessmentModels = Assessment::with('course')->withCount(['questions', 'questions as active_questions_count' => fn ($q) => $q->where('is_active', true), 'attempts'])->whereIn('id', $ids('assessment'))->get()->keyBy('id');
        $trainingModels = Training::with([
            'trainable' => fn (MorphTo $morph) => $morph->morphWith([Lesson::class => ['course'], Unit::class => ['course']]),
            'questions', 'quiz.questions',
        ])->withCount(['attempts' => fn ($q) => $q->whereNotNull('submitted_at')])->whereIn('id', $ids('training'))->get()->keyBy('id');
        $templateModels = QuestionBankQuiz::withCount(['questions', 'trainings', 'questions as active_questions_count' => fn ($q) => $q->where('question_bank_questions.is_active', true)])->whereIn('id', $ids('template'))->get()->keyBy('id');
        $records->setCollection($records->getCollection()->map(function ($row) use ($assessmentModels, $trainingModels, $templateModels) {
            $exam = match ($row->kind) {
                'assessment' => $assessmentModels[$row->id],
                'training' => $trainingModels[$row->id],
                default => $templateModels[$row->id],
            };
            $parent = $exam instanceof Training ? $exam->trainable : null;
            $course = $exam instanceof Assessment ? $exam->course : ($parent instanceof Course ? $parent : $parent?->course);
            $active = $exam instanceof Training ? $exam->questionSet()->count() : $exam->active_questions_count;
            $total = $exam instanceof Training ? ($exam->question_bank_quiz_id && $exam->quiz ? $exam->quiz->questions->count() : $exam->questions->count()) : $exam->questions_count;
            $status = $exam instanceof Training ? $exam->status : (($exam instanceof Assessment ? $exam->is_published : $exam->is_active) ? 'published' : 'draft');

            return [
                'id' => $exam->id, 'kind' => $row->kind,
                'title' => $exam instanceof Assessment ? ($course?->title ?? 'تقييم دورة') : ($exam instanceof Training ? $exam->title_ar : $exam->name_ar),
                'titleEn' => $exam instanceof Assessment ? $course?->title_en : ($exam instanceof Training ? $exam->title_en : $exam->name_en),
                'context' => $parent instanceof Lesson ? $parent->title : ($parent instanceof Unit ? $parent->title_ar : null),
                'course' => $course, 'status' => $status,
                'activeQuestions' => $active, 'totalQuestions' => $total,
                'questionLimit' => $exam instanceof QuestionBankQuiz ? null : $exam->question_limit,
                'passingScore' => $exam->passing_score ?: 70,
                'maxRetries' => $exam instanceof QuestionBankQuiz ? null : $exam->max_retries,
                'attempts' => $exam instanceof QuestionBankQuiz ? null : $exam->attempts_count,
                'uses' => $exam instanceof QuestionBankQuiz ? $exam->trainings_count : null,
                'availableFrom' => $exam instanceof QuestionBankQuiz ? null : $exam->available_from,
                'deadline' => $exam instanceof Assessment ? $exam->due_at : ($exam instanceof Training ? $exam->available_until : null),
                'settingsUrl' => match ($row->kind) {
                    'assessment' => route('admin.question-bank.assessment', $course),
                    'training' => route('admin.learning-paths.trainings.settings', $exam),
                    default => route('admin.question-bank.index', ['tab' => 'quizzes', 'quiz_id' => $exam->id]),
                },
                'resultsUrl' => match ($row->kind) {
                    'assessment' => route('admin.assessment.attempts', $course),
                    'training' => route('admin.exams.training-attempts', $exam),
                    default => null,
                },
                'stateUrl' => match ($row->kind) {
                    'assessment' => route('admin.assessment.state', $course),
                    'training' => route('admin.learning-paths.state', ['training', $exam->id]),
                    default => route('admin.question-bank.quizzes.toggle', $exam),
                },
            ];
        }));

        return view('admin.assessments.index', [
            'records' => $records, 'filters' => $filters, 'search' => $search,
            'courses' => Course::orderBy('title')->get(['id', 'title']),
            'stats' => ['assessment' => Assessment::count(), 'training' => Training::count(), 'template' => QuestionBankQuiz::count()],
        ]);
    }

    public function trainingAttempts(Request $request, Training $training): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:150'], 'page' => ['nullable', 'integer', 'min:1']]);
        $search = trim($filters['search'] ?? '');
        $query = $training->attempts()->whereNotNull('submitted_at');
        $count = (clone $query)->count();

        return view('admin.assessments.training-attempts', [
            'training' => $training, 'search' => $search,
            'summary' => [
                'attempts' => $count,
                'average' => round((float) (clone $query)->avg('score'), 1),
                'passRate' => $count ? round((clone $query)->where('status', 'passed')->count() / $count * 100, 1) : 0,
            ],
            'attempts' => $query->with('student:id,name,email')
                ->when($search, fn ($q) => $q->whereHas('student', fn ($s) => $s->where(fn ($s) => $s->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))))
                ->latest('submitted_at')->orderByDesc('id')->paginate(25)->withQueryString(),
        ]);
    }

    private function forCourse(Builder $query, string $courseId): void
    {
        $query->whereHasMorph('trainable', [Lesson::class, Unit::class, Course::class], fn ($parent, $kind) => $parent->where($kind === Course::class ? 'id' : 'course_id', $courseId));
    }

    private function searchCourse(Builder $query, string $search): void
    {
        $query->where(fn ($q) => $q->where('title', 'like', '%'.$search.'%')->orWhere('title_en', 'like', '%'.$search.'%')->orWhere('id', 'like', '%'.$search.'%'));
    }
}
