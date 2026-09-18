<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCohortController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cohorts = Cohort::query()->with(['course:id,title,title_en', 'teacher:id,name', 'academicTerm:id,name_ar,name_en'])->withCount('students')->latest()->paginate(min($request->integer('per_page', 50), 100));

        return response()->json($cohorts);
    }

    public function store(Request $request, AuditService $audit): JsonResponse
    {
        $data = $request->validate(['course_id' => ['required', 'exists:courses,id'], 'academic_term_id' => ['nullable', 'exists:academic_terms,id'], 'teacher_id' => ['nullable', 'exists:users,id'], 'name' => ['required', 'string', 'max:150'], 'code' => ['required', 'string', 'max:60', 'regex:/^[a-zA-Z0-9_-]+$/'], 'status' => ['sometimes', 'in:active,archived']]);
        if (! empty($data['teacher_id'])) {
            abort_unless(User::whereKey($data['teacher_id'])->where('role', User::ROLE_TEACHER)->exists(), 422, 'The owner must be a teacher.');
        }
        $cohort = Cohort::create($data + ['status' => 'active']);
        $audit->record($request, 'cohort.created', $cohort);

        return response()->json(['data' => $cohort->load(['course:id,title,title_en', 'teacher:id,name'])->loadCount('students')], 201);
    }

    public function addStudent(Request $request, Cohort $cohort, AuditService $audit): JsonResponse
    {
        $data = $request->validate(['student_id' => ['required', 'exists:users,id']]);
        abort_unless(User::whereKey($data['student_id'])->where('role', User::ROLE_STUDENT)->exists(), 422, 'Only students can join a cohort.');
        abort_unless($cohort->course->enrollments()->active()->where('user_id', $data['student_id'])->exists(), 422, 'The student must be active in the course.');
        $cohort->students()->syncWithoutDetaching([$data['student_id'] => ['added_by' => $request->user()->id]]);
        $audit->record($request, 'cohort.student_added', $cohort, ['student_id' => $data['student_id']]);

        return response()->json(['data' => ['cohortId' => $cohort->id, 'studentId' => $data['student_id']]]);
    }
}
