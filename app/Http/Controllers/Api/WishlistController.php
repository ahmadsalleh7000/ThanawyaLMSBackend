<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->wishlistItems()->latest()->pluck('course_id')->values(),
        ]);
    }

    public function toggle(Request $request, Course $course): JsonResponse
    {
        abort_unless($course->is_published, 404);

        $item = $request->user()->wishlistItems()->where('course_id', $course->id)->first();
        if ($item) {
            $item->delete();
            $saved = false;
        } else {
            $request->user()->wishlistItems()->create(['course_id' => $course->id]);
            $saved = true;
        }

        return response()->json(['data' => ['courseId' => $course->id, 'saved' => $saved]]);
    }
}
