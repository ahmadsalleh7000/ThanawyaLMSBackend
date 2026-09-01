<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicGrade;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuestionBankQuestion;
use App\Models\QuestionBankQuiz;
use App\Models\StudentSubscription;
use App\Models\Training;
use App\Models\Unit;
use App\Services\AcademicLearningPathService;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminLearningPathController extends Controller
{
    public function index(Request $request, AcademicLearningPathService $paths): View
    {
        $years = AcademicYear::orderByDesc('starts_on')->get();
        $grades = AcademicGrade::where('is_active', true)->orderBy('display_order')->get();
        $terms = AcademicTerm::with(['academicYear', 'grade'])->orderByDesc('academic_year_id')->orderBy('display_order')->get();
        $termQuery = AcademicTerm::with(['academicYear', 'grade'])
            ->when($request->integer('year'), fn ($query, $year) => $query->where('academic_year_id', $year))
            ->when($request->integer('grade'), fn ($query, $grade) => $query->where('academic_grade_id', $grade))
            ->orderByDesc('academic_year_id')->orderBy('display_order');
        $term = $request->integer('term')
            ? (clone $termQuery)->whereKey($request->integer('term'))->first()
            : null;
        $term ??= $termQuery->first();
        $requestedMode = $request->string('mode')->toString();
        $mode = in_array($requestedMode, ['curriculum', 'progress', 'general'], true) ? $requestedMode : 'curriculum';
        $students = $term ? StudentSubscription::active()->where('academic_term_id', $term->id)
            ->with('student:id,name,email')->get()->pluck('student')->filter()->unique('id')->sortBy('name')->values() : collect();
        $student = $students->firstWhere('id', $request->integer('student'));
        $path = $term ? ($mode === 'progress'
            ? ($student ? $paths->individualProgress($term, $student) : $paths->progress($term))
            : $paths->curriculum($term)) : null;

        return view('admin.learning-paths.index', compact('years', 'grades', 'terms', 'term', 'mode', 'path', 'students', 'student') + [
            'generalCourses' => Course::general()->notArchived()->withCount(['lessons', 'units'])->orderBy('position')->get(),
            'questionBank' => $mode === 'curriculum' ? QuestionBankQuestion::where('is_active', true)->orderBy('topic')->limit(150)->get(['id', 'type', 'topic', 'prompt_ar']) : collect(),
        ]);
    }

    public function storeUnit(Request $request, Course $course, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['slug' => ['required', 'regex:/^[a-z0-9-]+$/', 'max:80', Rule::unique('units')->where('course_id', $course->id)], 'title_ar' => ['required', 'string', 'max:150'], 'title_en' => ['required', 'string', 'max:150']]);
        $unit = $course->units()->create($data + ['academic_subject_id' => $course->academic_subject_id, 'position' => $course->units()->max('position') + 1, 'status' => 'draft']);
        $audit->record($request, 'learning_path.unit_created', $unit);

        if ($request->boolean('add_another')) {
            return back()->with('success', 'تمت إضافة الوحدة. يمكنك إضافة وحدة أخرى الآن.')->with('continue_unit_creation', true);
        }

        return back()->with('success', 'تمت إضافة الوحدة في موضعها داخل المسار.');
    }

    public function showCourse(Course $course): View|RedirectResponse
    {
        if ($course->isGeneral()) {
            return redirect()->route('admin.learning-paths.courses.units', $course);
        }
        $course->load([
            'academicSubject.term.academicYear', 'academicSubject.term.grade', 'academicSubject.subject',
            'instructorProfile', 'units.lessons.mediaAssets', 'units.lessons.trainings', 'assessment',
        ]);

        return view('admin.learning-paths.course', compact('course'));
    }

    public function units(Course $course): View
    {
        $course->load([
            'academicSubject.term.academicYear', 'academicSubject.term.grade', 'academicSubject.subject',
            'units' => fn ($query) => $query->withCount('lessons')->orderBy('position'),
        ]);

        return view('admin.learning-paths.units', compact('course'));
    }

    public function lessons(Course $course, Unit $unit): View
    {
        $this->ensureCourseOwnsUnit($course, $unit);
        $course->load('academicSubject.term.grade');
        $unit->load(['lessons' => fn ($query) => $query->withCount(['mediaAssets', 'trainings'])->orderBy('position')]);

        return view('admin.learning-paths.lessons', compact('course', 'unit'));
    }

    public function editUnit(Course $course, Unit $unit): View
    {
        $this->ensureCourseOwnsUnit($course, $unit);
        $course->load('academicSubject.term.grade');

        return view('admin.learning-paths.unit-edit', compact('course', 'unit'));
    }

    public function updateUnit(Request $request, Course $course, Unit $unit, AuditService $audit): RedirectResponse
    {
        $this->ensureCourseOwnsUnit($course, $unit);
        $data = $request->validate([
            'slug' => ['required', 'regex:/^[a-z0-9-]+$/', 'max:80', Rule::unique('units')->where('course_id', $course->id)->ignore($unit)],
            'title_ar' => ['required', 'string', 'max:150'],
            'title_en' => ['required', 'string', 'max:150'],
        ]);
        $before = $unit->attributesToArray();
        $unit->update($data);
        $audit->record($request, 'learning_path.unit_updated', $unit, $before);

        return redirect()->route('admin.learning-paths.courses.units', $course)->with('success', 'تم تحديث بيانات الوحدة.');
    }

    private function ensureCourseOwnsUnit(Course $course, Unit $unit): void
    {
        abort_unless($unit->course_id === $course->id, 404);
    }

    public function storeLesson(Request $request, Course $course, Unit $unit, AuditService $audit): RedirectResponse
    {
        abort_unless($unit->course_id === $course->id, 404);
        $data = $request->validate(['slug' => ['required', 'regex:/^[a-z0-9-]+$/', 'max:100', Rule::unique('lessons')->where('course_id', $course->id)], 'title' => ['required', 'string', 'max:255'], 'title_en' => ['required', 'string', 'max:255'], 'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440']]);
        $lesson = $course->lessons()->create($data + ['unit_id' => $unit->id, 'position' => $course->lessons()->max('position') + 1, 'is_preview' => false, 'status' => 'draft']);
        $audit->record($request, 'learning_path.lesson_created', $lesson);

        if ($request->boolean('add_another')) {
            return back()->with('success', 'تمت إضافة الدرس. يمكنك إضافة درس آخر الآن.')->with('continue_lesson_creation', true);
        }

        return back()->with('success', 'تمت إضافة الدرس داخل الوحدة المحددة.');
    }

    public function storeTraining(Request $request, string $type, int $id, AuditService $audit): RedirectResponse
    {
        $model = match ($type) {
            'course' => Course::findOrFail($id), 'unit' => Unit::findOrFail($id), 'lesson' => Lesson::findOrFail($id), default => abort(404)
        };
        $data = $request->validate([
            'question_bank_quiz_id' => ['nullable', Rule::exists('question_bank_quizzes', 'id')->where('is_active', true)],
            'type' => ['required_without:question_bank_quiz_id', Rule::in(['quiz', 'exercise', 'worksheet', 'assignment'])],
            'title_ar' => ['nullable', 'required_without:question_bank_quiz_id', 'string', 'max:180'],
            'title_en' => ['nullable', 'string', 'max:180'],
            'passing_score' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $quiz = isset($data['question_bank_quiz_id']) ? QuestionBankQuiz::find($data['question_bank_quiz_id']) : null;
        $training = $model->trainings()->create([
            'question_bank_quiz_id' => $quiz?->id,
            'type' => $data['type'] ?? ($quiz?->type === 'required' ? 'quiz' : 'exercise'),
            'title_ar' => ($data['title_ar'] ?? null) ?: $quiz?->name_ar,
            'title_en' => ($data['title_en'] ?? null) ?: ($quiz?->name_en ?: $quiz?->name_ar),
            'passing_score' => $data['passing_score'] ?? $quiz?->passing_score,
            'position' => $model->trainings()->max('position') + 1,
            'status' => 'draft',
            'is_required' => $quiz?->is_required ?? true,
        ]);
        $audit->record($request, 'learning_path.training_created', $training);

        return back()->with('success', 'تمت إضافة التدريب كمسودة.');
    }

    public function state(Request $request, string $type, int $id, AuditService $audit): RedirectResponse
    {
        $model = match ($type) {
            'unit' => Unit::findOrFail($id), 'training' => Training::findOrFail($id), default => abort(404)
        };
        $status = $request->validate(['status' => ['required', Rule::in(['draft', 'published', 'archived'])]])['status'];
        if ($model instanceof Unit && $status === 'published' && ! $model->lessons()->exists()) {
            return back()->withErrors(['status' => 'لا يمكن نشر وحدة فارغة. أضف درساً واحداً على الأقل ثم حاول مجدداً.']);
        }
        if ($model instanceof Training && $status === 'published' && $model->questionSet()->isEmpty()) {
            return back()->withErrors(['status' => 'لا يمكن نشر اختبار بدون أسئلة. أضف سؤالاً واحداً على الأقل ثم حاول مجدداً.']);
        }
        $before = $model->attributesToArray();
        $model->update(['status' => $status]);
        $audit->record($request, 'learning_path.status_changed', $model, $before);

        return back()->with('success', 'تم تحديث حالة المحتوى.');
    }

    public function attachQuestion(Request $request, Training $training, AuditService $audit): RedirectResponse
    {
        $questionId = $request->validate([
            'question_id' => ['required', Rule::exists('question_bank_questions', 'id')->where('is_active', true)],
        ])['question_id'];
        $nextPosition = (int) $training->questions()->max('question_bank_question_training.position') + 1;
        $training->questions()->syncWithoutDetaching([$questionId => ['position' => $nextPosition]]);
        $audit->record($request, 'learning_path.training_question_attached', $training, ['question_id' => $questionId]);

        return back()->with('success', 'تم ربط السؤال بالتدريب.');
    }

    public function destroyTraining(Request $request, Training $training, AuditService $audit): RedirectResponse
    {
        $parent = $training->trainable;
        $before = $training->attributesToArray();

        DB::transaction(function () use ($training, $parent): void {
            $training->questions()->detach();
            $training->delete();

            if ($parent && method_exists($parent, 'trainings')) {
                $parent->trainings()->get()->values()->each(
                    fn (Training $item, int $index) => $item->update(['position' => $index + 1])
                );
            }
        });

        $audit->record($request, 'learning_path.training_deleted', $training, $before);

        return back()->with('success', 'تم حذف الاختبار المرتبط من هذا الدرس.');
    }

    public function move(Request $request, string $type, string $id, string $direction, AuditService $audit): RedirectResponse
    {
        abort_unless(in_array($direction, ['up', 'down'], true), 404);
        $model = match ($type) {
            'course' => Course::findOrFail($id), 'unit' => Unit::findOrFail($id), default => abort(404)
        };
        $query = $type === 'course' ? Course::where('academic_subject_id', $model->academic_subject_id) : Unit::where('course_id', $model->course_id);
        $adjacent = $query->where('position', $direction === 'up' ? '<' : '>', $model->position)->orderBy('position', $direction === 'up' ? 'desc' : 'asc')->first();
        if ($adjacent) {
            DB::transaction(function () use ($model, $adjacent): void {
                $position = $model->position;
                $model->update(['position' => 65535]);
                $adjacentPosition = $adjacent->position;
                $adjacent->update(['position' => $position]);
                $model->update(['position' => $adjacentPosition]);
            });
        }
        $audit->record($request, 'learning_path.reordered', $model);

        return back()->with('success', 'تم تحديث الترتيب.');
    }
}
