<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\StudentContentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    public function __construct(private readonly StudentContentAccessService $accessService) {}

    public function access(Request $request, MediaAsset $media): JsonResponse
    {
        $media->loadMissing(['lesson.course', 'libraryItem']);
        abort_if($media->libraryItem && ($media->libraryItem->processing_status !== 'ready' || $media->libraryItem->archived_at), 409, 'Media is not ready.');
        $allowed = $media->lesson->is_preview || $this->accessService->canAccessMedia($request->user(), $media);
        abort_unless($allowed, 403, 'An active subscription is required to access this resource.');

        return response()->json(['data' => $this->signed($media)]);
    }

    public function preview(MediaAsset $media): JsonResponse
    {
        $media->loadMissing(['lesson.course', 'libraryItem']);
        abort_if($media->libraryItem && ($media->libraryItem->processing_status !== 'ready' || $media->libraryItem->archived_at), 404);
        $lesson = $media->lesson;
        $course = $lesson->course;
        abort_unless($course->is_published && ! $course->archived_at, 404);
        abort_unless($lesson->is_preview && $lesson->status === 'published', 404);
        abort_if($lesson->available_from?->isFuture() || $lesson->available_until?->isPast(), 404);
        abort_unless($media->status === 'published', 404);
        abort_if($media->available_from?->isFuture() || $media->available_until?->isPast(), 404);

        return response()->json(['data' => $this->signed($media)]);
    }

    public function stream(Request $request, MediaAsset $media): BinaryFileResponse
    {
        $media->loadMissing('libraryItem');
        abort_if($media->libraryItem && ($media->libraryItem->processing_status !== 'ready' || $media->libraryItem->archived_at), 404);
        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);
        $path = Storage::disk($media->disk)->path($media->path);
        $headers = ['Content-Type' => $media->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=600'];

        return $request->boolean('download')
            ? response()->download($path, $media->original_name, $headers)
            : response()->file($path, $headers);
    }

    private function signed(MediaAsset $media): array
    {
        return ['id' => $media->id, 'kind' => $media->kind, 'name' => $media->original_name, 'mimeType' => $media->mime_type, 'size' => $media->size_bytes, 'url' => URL::temporarySignedRoute('media.stream', now()->addMinutes(10), ['media' => $media->id, 'download' => $media->kind === 'resource' ? 1 : 0]), 'expiresAt' => now()->addMinutes(10)->toISOString()];
    }
}
