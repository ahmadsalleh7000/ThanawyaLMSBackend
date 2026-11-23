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
        $this->ensurePublished($media);
        $allowed = $media->lesson->is_preview || $this->accessService->canAccessMedia($request->user(), $media);
        abort_unless($allowed, 403, __('An active subscription is required to access this resource.'));

        return response()->json(['data' => $this->signed($media)]);
    }

    public function preview(MediaAsset $media): JsonResponse
    {
        $media->loadMissing(['lesson.course', 'libraryItem']);
        abort_if($media->libraryItem && ($media->libraryItem->processing_status !== 'ready' || $media->libraryItem->archived_at), 404);
        $this->ensurePublished($media);
        abort_unless($media->lesson->is_preview, 404);

        return response()->json(['data' => $this->signed($media)]);
    }

    public function stream(Request $request, MediaAsset $media): BinaryFileResponse
    {
        $this->ensurePublished($media);
        $media->loadMissing('libraryItem');
        abort_if($media->libraryItem && ($media->libraryItem->processing_status !== 'ready' || $media->libraryItem->archived_at), 404);
        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);
        $path = Storage::disk($media->disk)->path($media->path);
        $headers = ['Content-Type' => $media->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=600'];

        return $request->boolean('download')
            ? response()->download($path, $media->original_name, $headers)
            : response()->file($path, $headers);
    }

    public function offline(MediaAsset $media): BinaryFileResponse
    {
        $this->ensurePublished($media);
        abort_unless($media->lesson->is_preview && $media->lesson->allow_pdf_download
            && $media->kind === 'resource' && $media->mime_type === 'application/pdf', 404);
        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        return response()->file(Storage::disk($media->disk)->path($media->path), [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'public, max-age=3600', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function ensurePublished(MediaAsset $media): void
    {
        $media->loadMissing('lesson.course', 'lesson.unit', 'libraryItem');
        abort_if($media->libraryItem && ($media->libraryItem->processing_status !== 'ready' || $media->libraryItem->archived_at), 404);
        $lesson = $media->lesson;
        abort_unless($lesson && $lesson->course->is_published && ! $lesson->course->archived_at, 404);
        abort_unless($lesson->status === 'published' && $media->status === 'published', 404);
        foreach ([$lesson, $lesson->unit, $media] as $item) {
            if (! $item) {
                continue;
            }
            abort_if($item->available_from?->isFuture() || $item->available_until?->isPast(), 404);
        }
        abort_if($lesson->unit && $lesson->unit->status !== 'published', 404);
    }

    private function signed(MediaAsset $media): array
    {
        return ['id' => $media->id, 'kind' => $media->kind, 'name' => $media->original_name, 'mimeType' => $media->mime_type, 'size' => $media->size_bytes, 'url' => URL::temporarySignedRoute('media.stream', now()->addMinutes(10), ['media' => $media->id, 'download' => $media->kind === 'resource' ? 1 : 0]), 'expiresAt' => now()->addMinutes(10)->toISOString()];
    }
}
