<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParentStudentLink;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = validator($request->only(['role', 'status', 'linkage', 'sort', 'view']), [
            'role' => ['nullable', Rule::in(['student', 'parent'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'linkage' => ['nullable', Rule::in(['linked', 'unlinked'])],
            'sort' => ['nullable', Rule::in(['latest', 'name', 'activity'])],
            'view' => ['nullable', Rule::in(['table', 'grid'])],
        ])->validate();
        $query = User::whereIn('role', ['student', 'parent'])
            ->with(['linkedStudents:id,name', 'linkedParents:id,name'])
            ->withCount(['enrollments', 'linkedStudents', 'linkedParents']);
        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(fn ($inner) => $inner->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
        }
        $query->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('is_active', $status === 'active'))
            ->when(($filters['linkage'] ?? null) === 'linked', fn ($query) => $query->where(fn ($inner) => $inner->where(fn ($student) => $student->where('role', 'student')->has('linkedParents'))->orWhere(fn ($parent) => $parent->where('role', 'parent')->has('linkedStudents'))))
            ->when(($filters['linkage'] ?? null) === 'unlinked', fn ($query) => $query->where(fn ($inner) => $inner->where(fn ($student) => $student->where('role', 'student')->doesntHave('linkedParents'))->orWhere(fn ($parent) => $parent->where('role', 'parent')->doesntHave('linkedStudents'))));

        match ($filters['sort'] ?? 'latest') {
            'name' => $query->orderBy('name'),
            'activity' => $query->orderByRaw('(enrollments_count + linked_students_count + linked_parents_count) desc'),
            default => $query->latest(),
        };

        return view('admin.users.index', [
            'users' => $query->paginate(18)->withQueryString(),
            'parents' => User::parents()->where('is_active', true)->orderBy('name')->get(),
            'students' => User::students()->where('is_active', true)->orderBy('name')->get(),
            'stats' => [
                'students' => User::students()->count(),
                'parents' => User::parents()->count(),
                'active' => User::whereIn('role', ['student', 'parent'])->where('is_active', true)->count(),
                'unlinked' => User::whereIn('role', ['student', 'parent'])->where(fn ($inner) => $inner->where(fn ($student) => $student->where('role', 'student')->doesntHave('linkedParents'))->orWhere(fn ($parent) => $parent->where('role', 'parent')->doesntHave('linkedStudents')))->count(),
            ],
            'display' => $filters['view'] ?? 'table',
        ]);
    }

    public function show(User $user): View
    {
        abort_unless($user->role === User::ROLE_STUDENT, 404);

        $enrollments = $user->enrollments()
            ->with(['course' => fn ($query) => $query->withCount('lessons')])
            ->latest('enrolled_at')
            ->get();
        $completions = $user->lessonCompletions()->with('lesson:id,course_id,title,title_en')->latest('completed_at')->get();
        $attempts = $user->assessmentAttempts()->with('assessment.course:id,title,title_en')->latest('submitted_at')->get();
        $certificates = $user->certificates()->with('course:id,title,title_en')->latest('issued_at')->get();
        $orders = $user->orders()->with(['course:id,title,title_en', 'bundle:id,name,name_en', 'subscriptionPlan:id,name_ar,name_en'])->latest()->get();
        $completedByCourse = $completions->groupBy('lesson.course_id')->map->count();
        $attemptsByCourse = $attempts->groupBy('assessment.course_id');
        $certificatesByCourse = $certificates->keyBy('course_id');

        $courses = $enrollments->map(function ($enrollment) use ($completedByCourse, $attemptsByCourse, $certificatesByCourse) {
            $total = $enrollment->course->lessons_count;
            $completed = $completedByCourse->get($enrollment->course_id, 0);

            return [
                'enrollment' => $enrollment,
                'completed' => $completed,
                'total' => $total,
                'progress' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
                'attempt' => $attemptsByCourse->get($enrollment->course_id)?->first(),
                'certificate' => $certificatesByCourse->get($enrollment->course_id),
            ];
        });

        return view('admin.users.show', [
            'student' => $user,
            'parents' => $user->linkedParents()->withPivot(['grade', 'linked_at'])->get(),
            'courses' => $courses,
            'attempts' => $attempts,
            'certificates' => $certificates,
            'orders' => $orders,
            'recentCompletions' => $completions->take(8),
            'summary' => [
                'courses' => $enrollments->count(),
                'completedLessons' => $completions->count(),
                'averageProgress' => $courses->isEmpty() ? 0 : (int) round($courses->avg('progress')),
                'passedAssessments' => $attempts->where('passed', true)->count(),
                'totalPaid' => (float) $orders->where('status', 'completed')->sum('amount'),
            ],
        ]);
    }

    public function toggle(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        abort_unless(in_array($user->role, ['student', 'parent'], true), 403);
        $before = $user->attributesToArray();
        $user->update(['is_active' => ! $user->is_active]);
        if (! $user->is_active) {
            $user->tokens()->delete();
        }
        $audit->record($request, $user->is_active ? 'user.activated' : 'user.deactivated', $user, $before);

        return back()->with('success', 'تم تحديث حالة الحساب.');
    }

    public function link(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['parent_id' => ['required', Rule::exists('users', 'id')->where('role', 'parent')], 'student_id' => ['required', 'different:parent_id', Rule::exists('users', 'id')->where('role', 'student')], 'grade' => ['nullable', Rule::in(['grade-1', 'grade-2', 'grade-3'])]]);
        $link = ParentStudentLink::updateOrCreate(['parent_id' => $data['parent_id'], 'student_id' => $data['student_id']], ['grade' => $data['grade'] ?? null, 'status' => 'accepted', 'linked_at' => now(), 'accepted_at' => now(), 'verified_at' => now(), 'rejected_at' => null, 'revoked_at' => null]);
        $audit->record($request, $link->wasRecentlyCreated ? 'parent_student_link.created' : 'parent_student_link.updated', $link);

        return back()->with('success', 'تم حفظ رابط ولي الأمر والطالب.');
    }
}
