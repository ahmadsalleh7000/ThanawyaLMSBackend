<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\ParentStudentLink;
use App\Models\User;
use App\Services\AuditService;
use App\Services\StudentContentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'role' => ['nullable', Rule::in(['student', 'parent'])], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $query = User::query()->whereIn('role', ['student', 'parent'])->withCount(['enrollments', 'linkedStudents', 'linkedParents'])->latest();
        if ($search = $validated['search'] ?? null) {
            $query->where(fn ($inner) => $inner->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
        }
        if ($role = $validated['role'] ?? null) {
            $query->where('role', $role);
        }
        if ($status = $validated['status'] ?? null) {
            $query->where('is_active', $status === 'active');
        }
        $users = $query->limit(100)->get()->map(fn (User $user) => $this->summary($user));

        return response()->json(['data' => $users]);
    }

    public function show(User $user): JsonResponse
    {
        abort_unless(in_array($user->role, ['student', 'parent'], true), 404);
        $data = $this->summary($user);
        if ($user->role === 'student') {
            $data['parents'] = $user->linkedParents()->get()->map(fn (User $parent) => $parent->only(['id', 'name', 'email']));
            $data['courses'] = $this->studentCourses($user);
            $data['orders'] = $user->orders()->latest()->get()->map(fn ($order) => ['id' => $order->public_id, 'courseId' => $order->course_id, 'amount' => (float) $order->amount, 'currency' => $order->currency, 'status' => $order->status, 'date' => ($order->paid_at ?? $order->created_at)->toISOString()]);
        } else {
            $data['students'] = $user->linkedStudents()->get()->map(fn (User $student) => ['id' => $student->id, 'name' => $student->name, 'email' => $student->email, 'grade' => $student->pivot->grade]);
        }

        return response()->json(['data' => $data]);
    }

    public function activate(Request $request, User $user, AuditService $audit): JsonResponse
    {
        $this->ensureManaged($request, $user);
        $before = $user->attributesToArray();
        $user->update(['is_active' => true]);
        $audit->record($request, 'user.activated', $user, $before);

        return response()->json(['data' => $this->summary($user)]);
    }

    public function deactivate(Request $request, User $user, AuditService $audit): JsonResponse
    {
        $this->ensureManaged($request, $user);
        $before = $user->attributesToArray();
        $user->update(['is_active' => false]);
        $user->tokens()->delete();
        $audit->record($request, 'user.deactivated', $user, $before);

        return response()->json(['data' => $this->summary($user)]);
    }

    public function link(Request $request, AuditService $audit): JsonResponse
    {
        $validated = $request->validate(['parent_id' => ['required', 'integer', 'exists:users,id'], 'student_id' => ['required', 'integer', 'different:parent_id', 'exists:users,id'], 'grade' => ['nullable', Rule::in(['grade-1', 'grade-2', 'grade-3'])]]);
        $parent = User::whereKey($validated['parent_id'])->where('role', 'parent')->firstOrFail();
        $student = User::whereKey($validated['student_id'])->where('role', 'student')->firstOrFail();
        $link = ParentStudentLink::updateOrCreate(
            ['parent_id' => $parent->id, 'student_id' => $student->id],
            ['grade' => $validated['grade'] ?? null, 'status' => 'accepted', 'linked_at' => now(), 'accepted_at' => now(), 'verified_at' => now(), 'rejected_at' => null, 'revoked_at' => null],
        );
        $audit->record($request, $link->wasRecentlyCreated ? 'parent_student_link.created' : 'parent_student_link.updated', $link);

        return response()->json(['data' => ['id' => $link->id, 'parentId' => $parent->id, 'studentId' => $student->id, 'grade' => $link->grade]], $link->wasRecentlyCreated ? 201 : 200);
    }

    public function unlink(Request $request, User $parent, User $student, AuditService $audit): JsonResponse
    {
        $link = ParentStudentLink::where('parent_id', $parent->id)->where('student_id', $student->id)->firstOrFail();
        $before = $link->attributesToArray();
        $audit->record($request, 'parent_student_link.deleted', $link, $before);
        $link->delete();

        return response()->json(status: 204);
    }

    private function summary(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role, 'isActive' => $user->is_active, 'enrollmentCount' => $user->enrollments_count ?? $user->enrollments()->count(), 'linkedStudentCount' => $user->linked_students_count ?? $user->linkedStudents()->count(), 'linkedParentCount' => $user->linked_parents_count ?? $user->linkedParents()->count(), 'joinedAt' => $user->created_at->toISOString()];
    }

    private function studentCourses(User $student): array
    {
        $completedIds = $student->lessonCompletions()->pluck('lesson_id');

        return Course::available()
            ->whereIn('id', app(StudentContentAccessService::class)->courseIdsFor($student))
            ->with('lessons')
            ->get()
            ->map(function (Course $course) use ($student, $completedIds) {
            $lessonIds = $course->lessons->pluck('id');
            $completed = $lessonIds->intersect($completedIds)->count();
            $attempt = AssessmentAttempt::where('user_id', $student->id)->whereHas('assessment', fn ($query) => $query->where('course_id', $course->id))->latest('submitted_at')->first();

            return ['id' => $course->id, 'title' => $course->title, 'titleEn' => $course->title_en, 'progress' => $lessonIds->isEmpty() ? 0 : (int) round(($completed / $lessonIds->count()) * 100), 'completedLessons' => $completed, 'totalLessons' => $lessonIds->count(), 'assessmentScore' => $attempt?->score, 'certificateIssued' => Certificate::where('user_id', $student->id)->where('course_id', $course->id)->exists()];
        })->all();
    }

    private function ensureManaged(Request $request, User $user): void
    {
        abort_if($request->user()->is($user), 409, 'You cannot change your own account status.');
        abort_unless(in_array($user->role, ['student', 'parent'], true), 403, 'Administrator accounts cannot be changed here.');
    }
}
