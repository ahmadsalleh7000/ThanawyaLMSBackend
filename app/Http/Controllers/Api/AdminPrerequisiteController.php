<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CoursePrerequisite;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPrerequisiteController extends Controller
{
    public function store(Request $request, Course $course, AuditService $audit): JsonResponse
    {
        $data = $request->validate(['prerequisite_course_id' => ['required', 'exists:courses,id', 'different:course'], 'minimum_progress' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        abort_if($data['prerequisite_course_id'] === $course->id, 422, 'A course cannot require itself.');
        $prerequisite = CoursePrerequisite::updateOrCreate(['course_id' => $course->id, 'prerequisite_course_id' => $data['prerequisite_course_id']], ['minimum_progress' => $data['minimum_progress'] ?? 100]);
        $audit->record($request, 'course.prerequisite.updated', $prerequisite);

        return response()->json(['data' => $prerequisite->load('prerequisiteCourse:id,title,title_en')], 201);
    }

    public function destroy(Request $request, CoursePrerequisite $prerequisite, AuditService $audit): JsonResponse
    {
        $before = $prerequisite->attributesToArray();
        $prerequisite->delete();
        $audit->record($request, 'course.prerequisite.deleted', $prerequisite, $before);

        return response()->json(['data' => ['deleted' => true]]);
    }
}
