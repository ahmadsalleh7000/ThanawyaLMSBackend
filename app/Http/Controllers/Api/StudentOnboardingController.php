<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentOnboardingProfile;
use App\Services\StudentOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentOnboardingController extends Controller
{
    public function show(Request $request, StudentOnboardingService $service): JsonResponse
    {
        $profile = StudentOnboardingProfile::firstOrCreate(['student_id' => $request->user()->id]);

        return response()->json(['data' => $this->serialize($profile->load('recommendedCourse:id,title,title_en,summary,summary_en,level')), 'questions' => $service->questions()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'grade' => ['required', Rule::in(['grade-1', 'grade-2'])],
            'semester' => ['required', Rule::in(['semester-1', 'semester-2'])],
            'learning_goal' => ['required', Rule::in(['exam_success', 'programming_foundations', 'projects', 'career_exploration'])],
            'experience_level' => ['required', Rule::in(['none', 'basic', 'comfortable'])],
            'weekly_goal_minutes' => ['required', 'integer', Rule::in([60, 120, 180, 300])],
        ]);
        $profile = StudentOnboardingProfile::updateOrCreate(['student_id' => $request->user()->id], $data);

        return response()->json(['data' => $this->serialize($profile)]);
    }

    public function submitPlacement(Request $request, StudentOnboardingService $service): JsonResponse
    {
        $profile = StudentOnboardingProfile::where('student_id', $request->user()->id)->firstOrFail();
        $questionIds = collect($service->questions())->pluck('id')->all();
        $data = $request->validate(['answers' => ['required', 'array'], 'answers.*' => ['required', Rule::in(['a', 'b', 'c'])]]);
        abort_unless(empty(array_diff($questionIds, array_keys($data['answers']))) && empty(array_diff(array_keys($data['answers']), $questionIds)), 422, 'Answer every placement question.');

        return response()->json(['data' => $this->serialize($service->evaluate($profile, $data['answers']))]);
    }

    public function skip(Request $request): JsonResponse
    {
        $profile = StudentOnboardingProfile::updateOrCreate(
            ['student_id' => $request->user()->id],
            ['skipped_at' => now()],
        );

        return response()->json(['data' => $this->serialize($profile)]);
    }

    private function serialize(StudentOnboardingProfile $profile): array
    {
        return ['grade' => $profile->grade, 'semester' => $profile->semester, 'learningGoal' => $profile->learning_goal, 'experienceLevel' => $profile->experience_level, 'weeklyGoalMinutes' => $profile->weekly_goal_minutes, 'placementScore' => $profile->placement_score, 'recommendedLevel' => $profile->recommended_level, 'recommendedCourse' => $profile->recommendedCourse, 'completed' => (bool) $profile->completed_at, 'completedAt' => $profile->completed_at?->toISOString(), 'skipped' => (bool) $profile->skipped_at, 'skippedAt' => $profile->skipped_at?->toISOString()];
    }
}
