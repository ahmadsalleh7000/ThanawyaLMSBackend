<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\MediaAsset;
use App\Models\MediaLibraryItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MediaLibraryService
{
    public function upload(UploadedFile $file, string $kind, User $uploader): MediaLibraryItem
    {
        $mime = (string) $file->getMimeType();
        $this->validateType($kind, $mime, (int) $file->getSize());
        $path = $file->store('media-library/'.now()->format('Y/m'), 'local');
        abort_unless($path, 500, 'The media file could not be stored.');

        return MediaLibraryItem::create([
            'uploaded_by' => $uploader->id, 'kind' => $kind, 'disk' => 'local', 'path' => $path,
            'original_name' => $file->getClientOriginalName(), 'mime_type' => $mime, 'size_bytes' => $file->getSize(),
            'checksum_sha256' => hash_file('sha256', Storage::disk('local')->path($path)), 'processing_status' => 'ready',
        ]);
    }

    public function attach(MediaLibraryItem $item, Lesson $lesson): MediaAsset
    {
        abort_unless(! $item->archived_at && $item->processing_status === 'ready', 409, 'Media is not ready for attachment.');
        if ($item->kind === 'video') {
            $lesson->mediaAssets()->where('kind', 'video')->delete();
        }

        return $lesson->mediaAssets()->firstOrCreate(['media_library_item_id' => $item->id], [
            'kind' => $item->kind, 'disk' => $item->disk, 'path' => $item->path, 'original_name' => $item->original_name,
            'mime_type' => $item->mime_type, 'size_bytes' => $item->size_bytes,
        ]);
    }

    public function archive(MediaLibraryItem $item): void
    {
        abort_if($item->attachments()->exists(), 409, 'Detach this item from all lessons before archiving.');
        $item->update(['archived_at' => now()]);
    }

    private function validateType(string $kind, string $mime, int $size): void
    {
        $allowed = $kind === 'video' ? ['video/mp4', 'video/webm'] : ($kind === 'lesson_pdf' ? ['application/pdf'] : ['application/pdf', 'application/zip', 'text/plain', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
        if (! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages(['file' => 'نوع الملف غير مدعوم لهذا النوع من الوسائط.']);
        }
        if ($kind === 'resource' && $size > 25 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'حجم ملف المورد أكبر من الحد المسموح.']);
        }
    }
}
