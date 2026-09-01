<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\MediaAsset;
use App\Services\AuditService;
use App\Services\MediaLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminMediaController extends Controller
{
    public function store(Request $request, Course $course, Lesson $lesson, AuditService $audit, MediaLibraryService $library): JsonResponse
    {
        $this->ensureLesson($course, $lesson);
        abort_if($course->archived_at, 409, 'Archived courses cannot accept media.');
        $validated = $request->validate([
            'kind' => ['required', Rule::in(['video', 'resource', 'lesson_pdf'])],
            'file' => ['required', 'file', 'max:524288'],
        ]);
        $item = $library->upload($validated['file'], $validated['kind'], $request->user());
        $asset = $library->attach($item, $lesson);
        if ($asset->kind === 'lesson_pdf') {
            $lesson->update([
                'content_pdf_enabled' => true,
                'content_pdf_media_id' => $asset->id,
                'content_pdf_title' => $lesson->content_pdf_title ?: pathinfo($asset->original_name, PATHINFO_FILENAME),
            ]);
        }
        $audit->record($request, 'media.created', $asset);

        return response()->json(['data' => $this->serialize($asset)], 201);
    }

    public function destroy(Request $request, Course $course, Lesson $lesson, MediaAsset $media, AuditService $audit): JsonResponse
    {
        $this->ensureLesson($course, $lesson);
        abort_unless($media->lesson_id === $lesson->id, 404);
        $before = $media->attributesToArray();
        $audit->record($request, 'media.detached', $media, $before);
        if ((int) $lesson->content_pdf_media_id === (int) $media->id) {
            $lesson->update(['content_pdf_media_id' => null]);
        }
        $media->delete();

        return response()->json(status: 204);
    }

    private function ensureLesson(Course $course, Lesson $lesson): void
    {
        abort_unless($lesson->course_id === $course->id, 404);
    }

    private function serialize(MediaAsset $asset): array
    {
        return ['id' => $asset->id, 'kind' => $asset->kind, 'name' => $asset->original_name, 'mimeType' => $asset->mime_type, 'size' => $asset->size_bytes];
    }
}
