<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\User;
use App\Services\StudentContentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentRecommendationController extends Controller
{
    public function index(Request $request, StudentContentAccessService $access): JsonResponse
    {
        abort_unless($request->user()->role === User::ROLE_STUDENT, 403);
        $recommendations = collect();

        AssessmentAttempt::query()->where('user_id', $request->user()->id)
            ->whereNotNull('submitted_at')->with(['assessment.course', 'assessment.questions'])->latest('submitted_at')->limit(50)->get()
            ->each(function (AssessmentAttempt $attempt) use ($access, &$recommendations, $request): void {
                $course = $attempt->assessment?->course;
                if (! $course || ! $access->canAccessCourse($request->user(), $course)) {
                    return;
                }
                $answers = $attempt->answers ?: [];
                foreach ($attempt->assessment->questions as $question) {
                    $answer = $answers[$question->id] ?? $answers[(string) $question->id] ?? null;
                    $key = $course->id.'|'.($question->topic ?: 'general');
                    $item = $recommendations->get($key, ['courseId' => $course->id, 'course' => $course->title, 'courseEn' => $course->title_en, 'topic' => $question->topic ?: 'General practice', 'topicAr' => $question->topic_ar ?: 'تدريب عام', 'attempted' => 0, 'incorrect' => 0, 'score' => 0]);
                    $item['attempted']++;
                    if (! $this->correct($question, $answer)) {
                        $item['incorrect']++;
                    }
                    $item['score'] = (int) round((1 - ($item['incorrect'] / max($item['attempted'], 1))) * 100);
                    $recommendations->put($key, $item);
                }
            });

        return response()->json(['data' => $recommendations->filter(fn ($item) => $item['incorrect'] > 0)->sortBy(fn ($item) => [$item['score'], -$item['incorrect']])->take(5)->values()->map(fn ($item) => $item + ['url' => '/courses/'.$item['courseId'].'/assessment'])->all()]);
    }

    private function correct($question, mixed $answer): bool
    {
        if ($question->type === 'multiple_select') {
            $expected = array_map('intval', $question->correct_options ?? []);
            $actual = is_array($answer) ? array_map('intval', $answer) : [];
            sort($expected);
            sort($actual);

            return $actual === $expected;
        }
        if (in_array($question->type, ['short_answer', 'fill_in_blank'], true)) {
            return is_string($answer) && mb_strtolower(trim($answer)) === mb_strtolower(trim((string) $question->correct_answer));
        }

        return $answer !== null && (int) $answer === (int) $question->correct_option;
    }
}
