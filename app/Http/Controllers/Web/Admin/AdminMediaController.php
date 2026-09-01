<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\MediaAsset;
use App\Services\AuditService;
use App\Services\MediaLibraryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class AdminMediaController extends Controller
{
    public function storeLessonPdf(Request $request, Course $course, Lesson $lesson, AuditService $audit, MediaLibraryService $library): RedirectResponse
    {
        abort_unless($lesson->course_id === $course->id, 404);

        Log::info('lesson_pdf.upload.started', [
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'content_length' => $request->server('CONTENT_LENGTH'),
            'has_file' => $request->hasFile('file'),
        ]);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:524288'],
        ]);

        try {
            $asset = $this->storeAndAttach($data['file'], 'lesson_pdf', $lesson, $request, $audit, $library);

            Log::info('lesson_pdf.upload.completed', [
                'lesson_id' => $lesson->id,
                'media_asset_id' => $asset->id,
                'original_name' => $asset->original_name,
                'size_bytes' => $asset->size_bytes,
            ]);

            return redirect()
                ->route('admin.lessons.edit', [$course, $lesson, 'tab' => 'pdf'])
                ->with('success', 'تم رفع ملف PDF وربطه بالدرس.');
        } catch (Throwable $exception) {
            Log::error('lesson_pdf.upload.failed', [
                'course_id' => $course->id,
                'lesson_id' => $lesson->id,
                'class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('admin.lessons.edit', [$course, $lesson, 'tab' => 'pdf'])
                ->withInput()
                ->with('error', 'تعذر رفع ملف PDF. تأكد من أن الملف PDF صالح وأن هناك مساحة تخزين كافية ثم حاول مرة أخرى.');
        }
    }

    public function store(Request $request, Course $course, Lesson $lesson, AuditService $audit, MediaLibraryService $library): RedirectResponse
    {
        abort_unless($lesson->course_id === $course->id, 404);
        $rules = ['kind' => ['required', Rule::in(['video', 'resource', 'lesson_pdf'])], 'file' => ['required', 'file', 'max:524288']];
        if ($request->input('kind') === 'lesson_pdf') {
            $rules['file'][] = 'mimes:pdf';
        }
        $data = $request->validate($rules);
        $asset = $this->storeAndAttach($data['file'], $data['kind'], $lesson, $request, $audit, $library);

        $tab = $asset->kind === 'lesson_pdf' ? 'pdf' : 'media';

        return redirect()->route('admin.lessons.edit', [$course, $lesson, 'tab' => $tab])->with('success', 'تم رفع الملف الخاص.');
    }

    public function destroy(Request $request, Course $course, Lesson $lesson, MediaAsset $media, AuditService $audit): RedirectResponse
    {
        abort_unless($lesson->course_id === $course->id && $media->lesson_id === $lesson->id, 404);
        $before = $media->attributesToArray();
        $audit->record($request, 'media.detached', $media, $before);
        if ((int) $lesson->content_pdf_media_id === (int) $media->id) {
            $lesson->update(['content_pdf_media_id' => null]);
        }
        $media->delete();

        return back()->with('success', 'تم فصل الملف عن الدرس، وبقي محفوظاً في المكتبة.');
    }

    private function storeAndAttach($file, string $kind, Lesson $lesson, Request $request, AuditService $audit, MediaLibraryService $library): MediaAsset
    {
        $item = $library->upload($file, $kind, $request->user());
        $asset = $library->attach($item, $lesson);
        if ($asset->kind === 'lesson_pdf') {
            $lesson->update([
                'content_pdf_enabled' => true,
                'content_pdf_media_id' => $asset->id,
                'content_pdf_title' => $lesson->content_pdf_title ?: pathinfo($asset->original_name, PATHINFO_FILENAME),
            ]);
        }
        $audit->record($request, 'media.created', $asset);

        return $asset;
    }
}
