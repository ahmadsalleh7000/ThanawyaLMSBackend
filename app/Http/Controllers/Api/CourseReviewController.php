<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseReview;
use App\Services\StudentContentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseReviewController extends Controller
{
    public function __construct(private readonly StudentContentAccessService $access) {}

    public function index(Course $course): JsonResponse
    {
        $request = request();
        $sort = $request->validate(['sort' => ['nullable', 'in:recent,highest,lowest'], 'page' => ['nullable', 'integer', 'min:1']])['sort'] ?? 'recent';
        $query = $course->reviews()->published()->with('user:id,name');
        match ($sort) {
            'highest' => $query->orderByDesc('rating')->latest('moderated_at'),
            'lowest' => $query->orderBy('rating')->latest('moderated_at'),
            default => $query->latest('moderated_at'),
        };
        $reviews = $query->paginate(8)->through(fn (CourseReview $review): array => [
            'id' => $review->id,
            'name' => $review->user->name,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'verifiedEnrollment' => true,
            'date' => $review->moderated_at?->toISOString() ?? $review->created_at->toISOString(),
        ]);
        $user = $request->user('sanctum');
        $ownReview = $user ? $course->reviews()->where('user_id', $user->id)->first() : null;

        return response()->json($reviews->toArray() + ['meta' => [
            'averageRating' => round((float) $course->reviews()->published()->avg('rating'), 1),
            'reviewCount' => $course->reviews()->published()->count(),
            'canReview' => $user?->role === 'student' && $this->access->canAccessCourse($user, $course),
            'ownReview' => $ownReview ? ['id' => $ownReview->id, 'rating' => $ownReview->rating, 'comment' => $ownReview->comment, 'status' => $ownReview->status, 'moderationNote' => $ownReview->moderation_note] : null,
        ]]);
    }

    public function store(Request $request, Course $course): JsonResponse
    {
        abort_unless($request->user()->role === 'student', 403);
        abort_unless($this->access->canAccessCourse($request->user(), $course), 403, 'An active subscription is required to review this course.');
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['required', 'string', 'min:10', 'max:2000']]);
        $review = CourseReview::updateOrCreate(
            ['user_id' => $request->user()->id, 'course_id' => $course->id],
            $data + ['status' => 'pending', 'moderated_by' => null, 'moderated_at' => null, 'moderation_note' => null],
        );

        return response()->json(['message' => 'Review submitted for moderation.', 'review' => $review], $review->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, Course $course): JsonResponse
    {
        abort_unless($request->user()->role === 'student', 403);
        $review = $course->reviews()->where('user_id', $request->user()->id)->firstOrFail();
        $review->delete();

        return response()->json(status: 204);
    }
}
