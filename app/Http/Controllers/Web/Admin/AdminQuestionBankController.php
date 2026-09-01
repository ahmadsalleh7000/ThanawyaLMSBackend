<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\QuestionBankQuestion;
use App\Models\QuestionBankQuiz;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminQuestionBankController extends Controller
{
    public function index(Request $request): View
    {
        $filters = validator($request->only(['difficulty', 'type', 'status', 'topic', 'usage', 'tab', 'view']), [
            'difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard'])],
            'type' => ['nullable', Rule::in(['single_choice', 'true_false'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'topic' => ['nullable', 'string', 'max:150'],
            'usage' => ['nullable', Rule::in(['used', 'unused'])],
            'tab' => ['nullable', Rule::in(['questions', 'quizzes'])],
            'view' => ['nullable', Rule::in(['table', 'grid'])],
        ])->validate();
        $search = $request->string('search')->trim()->toString();
        $quizSearch = $request->string('quiz_search')->trim()->toString();
        $questions = QuestionBankQuestion::query()->withCount(['assessmentQuestions', 'quizzes'])
            ->when($search, fn ($query) => $query->where(fn ($inner) => $inner->where('prompt', 'like', '%'.$search.'%')->orWhere('prompt_ar', 'like', '%'.$search.'%')->orWhere('topic', 'like', '%'.$search.'%')->orWhere('topic_ar', 'like', '%'.$search.'%')))
            ->when($filters['difficulty'] ?? null, fn ($query, $difficulty) => $query->where('difficulty', $difficulty))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('is_active', $status === 'active'))
            ->when($filters['topic'] ?? null, fn ($query, $topic) => $query->where('topic_ar', $topic))
            ->when(($filters['usage'] ?? null) === 'used', fn ($query) => $query->has('quizzes'))
            ->when(($filters['usage'] ?? null) === 'unused', fn ($query) => $query->doesntHave('quizzes'))
            ->orderByDesc('is_active')->latest()->paginate(12)->withQueryString();

        return view('admin.question-bank.index', [
            'questions' => $questions,
            'quizzes' => QuestionBankQuiz::query()->with('questions')->withCount('questions')
                ->when($quizSearch, fn ($query) => $query->where(fn ($inner) => $inner->where('name_ar', 'like', '%'.$quizSearch.'%')->orWhere('name_en', 'like', '%'.$quizSearch.'%')))
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('is_active', $status === 'active'))
                ->latest()->paginate(8, ['*'], 'quiz_page')->withQueryString(),
            'activeQuestions' => QuestionBankQuestion::where('is_active', true)->withCount('quizzes')->orderBy('topic_ar')->limit(80)->get(['id', 'type', 'difficulty', 'topic_ar', 'prompt_ar']),
            'search' => $search,
            'quizSearch' => $quizSearch,
            'activeTab' => $filters['tab'] ?? 'questions',
            'display' => $filters['view'] ?? 'table',
            'topics' => QuestionBankQuestion::query()->select('topic_ar')->distinct()->orderBy('topic_ar')->pluck('topic_ar'),
            'stats' => [
                'total' => QuestionBankQuestion::count(),
                'single_choice' => QuestionBankQuestion::where('type', 'single_choice')->count(),
                'true_false' => QuestionBankQuestion::where('type', 'true_false')->count(),
                'unused' => QuestionBankQuestion::whereDoesntHave('quizzes')->count(),
                'quizzes' => QuestionBankQuiz::count(),
            ],
        ]);
    }

    public function assessment(Course $course): View
    {
        $assessment = $course->assessment()->with('questions')->firstOrFail();

        return view('admin.question-bank.assessment', [
            'course' => $course, 'assessment' => $assessment,
            'questions' => QuestionBankQuestion::where('is_active', true)->latest()->paginate(30),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $question = QuestionBankQuestion::create($this->data($request) + ['created_by' => $request->user()->id, 'is_active' => true]);
        $audit->record($request, 'question_bank.created', $question);

        return back()->with('success', 'تمت إضافة السؤال إلى البنك.');
    }

    public function update(Request $request, QuestionBankQuestion $question, AuditService $audit): RedirectResponse
    {
        $before = $question->attributesToArray();
        $question->update($this->data($request));
        $audit->record($request, 'question_bank.updated', $question, $before);

        return back()->with('success', 'تم تحديث سؤال البنك. لن تتغير النسخ المستخدمة سابقاً.');
    }

    public function toggle(Request $request, QuestionBankQuestion $question, AuditService $audit): RedirectResponse
    {
        $before = $question->attributesToArray();
        $question->update(['is_active' => ! $question->is_active]);
        $audit->record($request, $question->is_active ? 'question_bank.activated' : 'question_bank.deactivated', $question, $before);

        return back()->with('success', 'تم تحديث حالة السؤال.');
    }

    public function duplicate(Request $request, QuestionBankQuestion $question, AuditService $audit): RedirectResponse
    {
        $copy = $question->replicate(['created_by', 'created_at', 'updated_at']);
        $copy->created_by = $request->user()->id;
        $copy->prompt_ar = 'نسخة من '.$question->prompt_ar;
        $copy->is_active = true;
        $copy->save();
        $audit->record($request, 'question_bank.duplicated', $copy, ['source_id' => $question->id]);

        return back()->with('success', 'تم نسخ السؤال. يمكنك تعديله الآن بشكل مستقل.');
    }

    public function storeQuiz(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:180'],
            'name_en' => ['nullable', 'string', 'max:180'],
            'type' => ['required', Rule::in(QuestionBankQuiz::TYPES)],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'passing_score' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $quiz = QuestionBankQuiz::create($data + [
            'is_required' => $data['type'] === 'required',
            'created_by' => $request->user()->id,
            'is_active' => true,
        ]);
        $audit->record($request, 'question_bank_quiz.created', $quiz);

        return redirect()->route('admin.question-bank.index', ['tab' => 'quizzes'])->with('success', 'تم إنشاء الاختبار بنجاح.');
    }

    public function attachQuizQuestion(Request $request, QuestionBankQuiz $quiz, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'question_id' => ['nullable', Rule::exists('question_bank_questions', 'id')->where('is_active', true), Rule::unique('question_bank_question_quiz', 'question_bank_question_id')->where('question_bank_quiz_id', $quiz->id)],
            'question_ids' => ['nullable', 'array', 'min:1', 'max:50'],
            'question_ids.*' => ['integer', Rule::exists('question_bank_questions', 'id')->where('is_active', true)],
        ]);
        $ids = collect($data['question_ids'] ?? [$data['question_id'] ?? null])->filter()->unique()->values();
        abort_if($ids->isEmpty(), 422, 'اختر سؤالاً واحداً على الأقل.');

        $nextPosition = (int) $quiz->questions()->max('question_bank_question_quiz.position') + 1;
        $existing = $quiz->questions()->whereIn('question_bank_questions.id', $ids)->pluck('question_bank_questions.id');
        $payload = [];
        foreach ($ids->diff($existing) as $questionId) {
            $payload[$questionId] = ['position' => $nextPosition++];
        }
        abort_if(empty($payload), 422, 'هذه الأسئلة موجودة بالفعل في الاختبار.');
        $quiz->questions()->attach($payload);
        $audit->record($request, 'question_bank_quiz.questions_attached', $quiz, ['question_ids' => array_keys($payload)]);

        return back()->with('success', 'تمت إضافة '.count($payload).' سؤال للاختبار.');
    }

    public function duplicateQuiz(Request $request, QuestionBankQuiz $quiz, AuditService $audit): RedirectResponse
    {
        $copy = DB::transaction(function () use ($request, $quiz) {
            $copy = $quiz->replicate(['created_by', 'created_at', 'updated_at']);
            $copy->name_ar = 'نسخة من '.$quiz->name_ar;
            $copy->created_by = $request->user()->id;
            $copy->is_active = false;
            $copy->save();
            $copy->questions()->attach($quiz->questions->mapWithKeys(fn ($question) => [$question->id => ['position' => $question->pivot->position]])->all());

            return $copy;
        });
        $audit->record($request, 'question_bank_quiz.duplicated', $copy, ['source_id' => $quiz->id]);

        return redirect()->route('admin.question-bank.index', ['tab' => 'quizzes'])->with('success', 'تم نسخ الاختبار كمسودة غير نشطة.');
    }

    public function detachQuizQuestion(Request $request, QuestionBankQuiz $quiz, QuestionBankQuestion $question, AuditService $audit): RedirectResponse
    {
        abort_unless($quiz->questions()->whereKey($question->id)->exists(), 404);
        $quiz->questions()->detach($question->id);
        $this->normalizeQuizQuestionPositions($quiz);
        $audit->record($request, 'question_bank_quiz.question_detached', $quiz, ['question_id' => $question->id]);

        return back()->with('success', 'تمت إزالة السؤال من الاختبار فقط. بقي السؤال محفوظاً في بنك الأسئلة.');
    }

    public function moveQuizQuestion(Request $request, QuestionBankQuiz $quiz, QuestionBankQuestion $question, string $direction, AuditService $audit): RedirectResponse
    {
        abort_unless(in_array($direction, ['up', 'down'], true), 404);
        $current = $quiz->questions()->whereKey($question->id)->firstOrFail();
        $operator = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'desc' : 'asc';
        $adjacent = $quiz->questions()
            ->wherePivot('position', $operator, $current->pivot->position)
            ->orderByPivot('position', $order)
            ->first();

        if (! $adjacent) {
            return back();
        }

        DB::transaction(function () use ($quiz, $current, $adjacent): void {
            $quiz->questions()->updateExistingPivot($current->id, ['position' => $adjacent->pivot->position]);
            $quiz->questions()->updateExistingPivot($adjacent->id, ['position' => $current->pivot->position]);
        });
        $audit->record($request, 'question_bank_quiz.questions_reordered', $quiz, ['question_id' => $question->id, 'direction' => $direction]);

        return back()->with('success', 'تم حفظ ترتيب الأسئلة.');
    }

    public function toggleQuiz(Request $request, QuestionBankQuiz $quiz, AuditService $audit): RedirectResponse
    {
        $before = $quiz->attributesToArray();
        $quiz->update(['is_active' => ! $quiz->is_active]);
        $audit->record($request, $quiz->is_active ? 'question_bank_quiz.activated' : 'question_bank_quiz.deactivated', $quiz, $before);

        return back()->with('success', 'تم تحديث حالة الاختبار.');
    }

    public function import(Request $request, Course $course, AuditService $audit): RedirectResponse
    {
        $assessment = $course->assessment()->firstOrFail();
        abort_if($assessment->is_published, 409, 'Unpublish before importing questions.');
        $ids = $request->validate(['question_ids' => ['required', 'array', 'min:1', 'max:100'], 'question_ids.*' => ['integer', 'exists:question_bank_questions,id']])['question_ids'];
        $position = (int) $assessment->questions()->max('position');
        foreach (QuestionBankQuestion::where('is_active', true)->whereIn('id', $ids)->get() as $bankQuestion) {
            $position++;
            $copy = $assessment->questions()->create([
                'question_bank_question_id' => $bankQuestion->id, 'type' => $bankQuestion->type, 'difficulty' => $bankQuestion->difficulty,
                'topic' => $bankQuestion->topic, 'topic_ar' => $bankQuestion->topic_ar, 'prompt' => $bankQuestion->prompt, 'prompt_ar' => $bankQuestion->prompt_ar,
                'options' => $bankQuestion->options, 'options_ar' => $bankQuestion->options_ar, 'correct_option' => $bankQuestion->correct_option,
                'explanation' => $bankQuestion->explanation, 'explanation_ar' => $bankQuestion->explanation_ar, 'position' => $position,
            ]);
            $audit->record($request, 'assessment_question.imported', $copy);
        }

        return back()->with('success', 'تم استيراد الأسئلة المختارة إلى التقييم كنسخ مستقلة.');
    }

    private function data(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['single_choice', 'true_false'])], 'difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])],
            'topic' => ['required', 'string', 'max:150'], 'topic_ar' => ['required', 'string', 'max:150'],
            'prompt' => ['required', 'string', 'max:3000'], 'prompt_ar' => ['required', 'string', 'max:3000'],
            'options_text' => ['nullable', 'required_if:type,single_choice', 'string'], 'options_ar_text' => ['nullable', 'required_if:type,single_choice', 'string'],
            'correct_option' => ['required', 'integer', 'min:0'], 'explanation' => ['required', 'string', 'max:3000'], 'explanation_ar' => ['required', 'string', 'max:3000'],
        ]);
        [$options, $optionsAr] = $data['type'] === 'true_false'
            ? [['True', 'False'], ['صحيح', 'خطأ']]
            : [$this->lines($data['options_text']), $this->lines($data['options_ar_text'])];
        abort_unless(count($options) >= 2 && count($options) === count($optionsAr) && $data['correct_option'] < count($options), 422, 'يجب إدخال اختيارات واضحة وتحديد إجابة صحيحة واحدة.');
        unset($data['options_text'], $data['options_ar_text']);

        return $data + ['options' => $options, 'options_ar' => $optionsAr];
    }

    private function lines(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', $value)))));
    }

    private function normalizeQuizQuestionPositions(QuestionBankQuiz $quiz): void
    {
        $quiz->questions()->get()->values()->each(
            fn (QuestionBankQuestion $question, int $index) => $quiz->questions()->updateExistingPivot($question->id, ['position' => $index + 1])
        );
    }
}
