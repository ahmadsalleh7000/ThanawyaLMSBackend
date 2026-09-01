<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\EnrollmentLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function store(Request $request, Course $course, EnrollmentLifecycleService $enrollments): JsonResponse
    {
        abort_unless($course->is_published && ! $course->archived_at, 404);
        abort_if((float) $course->price > 0, 422, 'Paid courses require a confirmed payment.');
        $existing = $request->user()->enrollments()->where('course_id', $course->id)->first();
        $enrollment = $enrollments->enroll($request->user(), $course, 'self');

        return response()->json(['courseId' => $course->id, 'enrolledAt' => $enrollment->enrolled_at], $existing ? 200 : 201);
    }
}
