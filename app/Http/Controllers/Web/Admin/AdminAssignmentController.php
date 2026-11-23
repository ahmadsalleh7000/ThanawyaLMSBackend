<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertAssignmentRequest;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Lesson;
use App\Services\AssignmentWorkflowService;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'course_id' => ['nullable', 'string', 'exists:courses,id'],
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
            'status' => ['nullable', Rule::in(['published', 'draft'])],
        ]);
        $query = Assignment::with('lesson.course')->withCount([
            'submissions', 'submissions as pending_count' => fn ($q) => $q->whereIn('status', ['submitted', 'late', 'resubmitted']),
        ]);
        if ($search = trim($filters['search'] ?? '')) {
            $query->where(fn ($q) => $q->where('title', 'like', '%'.$search.'%')->orWhere('title_en', 'like', '%'.$search.'%')
                ->orWhereHas('lesson', fn ($q) => $q->where('title', 'like', '%'.$search.'%')->orWhere('title_en', 'like', '%'.$search.'%')));
        }
        if ($filters['course_id'] ?? null) {
            $query->whereHas('lesson', fn ($q) => $q->where('course_id', $filters['course_id']));
        }
        if ($filters['lesson_id'] ?? null) {
            $query->where('lesson_id', $filters['lesson_id']);
        }
        if ($filters['status'] ?? null) {
            $query->where('is_published', $filters['status'] === 'published');
        }

        return view('admin.assignments.index', [
            'assignments' => $query->latest('updated_at')->orderByDesc('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'courses' => Course::orderBy('title')->get(['id', 'title']),
            'selectedLesson' => isset($filters['lesson_id']) ? Lesson::with('course')->find($filters['lesson_id']) : null,
        ]);
    }

    public function create(Request $request): View
    {
        $data = $request->validate(['course_id' => ['nullable', 'string', 'exists:courses,id'], 'lesson_id' => ['nullable', 'integer', 'exists:lessons,id']]);
        $lesson = isset($data['lesson_id']) ? Lesson::with('course')->findOrFail($data['lesson_id']) : null;
        $course = $lesson?->course ?? (isset($data['course_id']) ? Course::findOrFail($data['course_id']) : null);

        return view('admin.assignments.form', [
            'assignment' => new Assignment(['lesson_id' => $lesson?->id, 'position' => $lesson ? min(1000, ($lesson->assignments()->max('position') ?? 0) + 1) : 1]),
            'course' => $course,
            'courses' => Course::whereNull('archived_at')->orderBy('title')->get(['id', 'title']),
            'lessons' => $course?->lessons()->orderBy('position')->get() ?? collect(),
        ]);
    }

    public function store(UpsertAssignmentRequest $request, AuditService $audit): RedirectResponse
    {
        $lessonId = $request->validate(['lesson_id' => ['required', 'integer', 'exists:lessons,id']])['lesson_id'];
        $lesson = Lesson::with('course')->findOrFail($lessonId);
        abort_if($lesson->course->archived_at, 409, 'Archived course assignments cannot be changed.');
        $assignment = $lesson->assignments()->create($request->validated() + ['position' => min(1000, ($lesson->assignments()->max('position') ?? 0) + 1)]);
        $audit->record($request, 'admin.assignment.created', $assignment);

        return redirect()->route('admin.assignments.edit', $assignment)->with('success', 'تم إنشاء المهمة.');
    }

    public function edit(Assignment $assignment): View
    {
        return view('admin.assignments.form', ['assignment' => $assignment->load('lesson.course'), 'course' => $assignment->lesson->course]);
    }

    public function update(UpsertAssignmentRequest $request, Assignment $assignment, AuditService $audit): RedirectResponse
    {
        abort_if($assignment->lesson->course->archived_at, 409, 'Archived course assignments cannot be changed.');
        $before = $assignment->attributesToArray();
        $assignment->update($request->validated());
        $audit->record($request, 'admin.assignment.updated', $assignment, $before);

        return back()->with('success', 'تم تحديث المهمة.');
    }

    public function destroy(Request $request, Assignment $assignment, AuditService $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $assignment, $audit) {
            $assignment = Assignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            abort_if($assignment->lesson->course->archived_at || $assignment->is_published || $assignment->submissions()->exists(), 409, 'Only unpublished assignments without submissions can be deleted.');
            $audit->record($request, 'admin.assignment.deleted', $assignment, $assignment->attributesToArray());
            $assignment->delete();
        });

        return redirect()->route('admin.assignments.index')->with('success', 'تم حذف المهمة.');
    }

    public function submissions(Request $request, Assignment $assignment): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['submitted', 'late', 'resubmitted', 'returned', 'graded'])],
        ]);
        $query = $assignment->submissions()->with('user:id,name,email');
        if ($search = trim($filters['search'] ?? '')) {
            $query->whereHas('user', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')));
        }
        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }

        return view('admin.assignments.submissions', [
            'assignment' => $assignment->load('lesson.course'), 'filters' => $filters,
            'submissions' => $query->latest('submitted_at')->orderByDesc('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function review(Request $request, Assignment $assignment, AssignmentSubmission $submission, AssignmentWorkflowService $workflow): RedirectResponse
    {
        abort_unless($submission->assignment_id === $assignment->id, 404);
        $data = $request->validate(['action' => ['required', Rule::in(['grade', 'return'])]]);
        $workflow->review($request, $submission, $data['action'] === 'return', 'admin');

        return back()->with('success', $data['action'] === 'return' ? 'تمت إعادة الحل للطالب للتعديل.' : 'تم حفظ الدرجة وإبلاغ الطالب.');
    }

    public function file(Assignment $assignment, AssignmentSubmission $submission, AssignmentWorkflowService $workflow): BinaryFileResponse
    {
        abort_unless($submission->assignment_id === $assignment->id, 404);

        return $workflow->download($submission);
    }
}
