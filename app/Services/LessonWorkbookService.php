<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class LessonWorkbookService
{
    public function prepare(Lesson $lesson, string $edition = 'student'): array
    {
        $lesson->loadMissing([
            'course.academicSubject',
            'unit',
            'contentPdfMedia.libraryItem',
            'mediaAssets.libraryItem',
            'trainings.questions',
            'trainings.quiz.questions',
            'sections',
        ]);

        $resources = $lesson->mediaAssets
            ->filter(fn (MediaAsset $media) => $media->kind === 'resource' && $media->status === 'published')
            ->values();

        $practice = $lesson->trainings
            ->first(fn ($training) => $training->status === 'published' && $training->questions->isNotEmpty());

        return [
            'lesson' => $lesson,
            'course' => $lesson->course,
            'unit' => $lesson->unit,
            'resources' => $resources,
            'practice' => $practice,
            'pdfUrl' => $this->lessonPdfUrl($lesson),
            'printDate' => now(),
            'brandName' => config('app.name', 'TIBYAN'),
            'edition' => $edition,
            'includeAnswerKey' => $edition === 'teacher' || (bool) $lesson->include_workbook_answer_key,
        ];
    }

    public function generatePdf(Lesson $lesson, string $edition = 'student'): array
    {
        $htmlPath = tempnam(sys_get_temp_dir(), 'lesson-workbook-html-').'.html';
        $pdfPath = tempnam(sys_get_temp_dir(), 'lesson-workbook-pdf-').'.pdf';

        File::put($htmlPath, view('workbooks.lesson', $this->prepare($lesson, $edition) + ['pdfMode' => true])->render());

        $process = new Process([
            'node',
            base_path('scripts/render-workbook-pdf.mjs'),
            $htmlPath,
            $pdfPath,
        ], timeout: 45);

        $process->run();
        @unlink($htmlPath);

        if (! $process->isSuccessful() || ! File::exists($pdfPath) || File::size($pdfPath) === 0) {
            @unlink($pdfPath);
            throw new RuntimeException('Workbook PDF generation failed: '.$process->getErrorOutput());
        }

        return [
            'path' => $pdfPath,
            'filename' => $this->filename($lesson, $edition),
        ];
    }

    public function filename(Lesson $lesson, string $edition = 'student'): string
    {
        $slug = Str::slug($lesson->slug ?: $lesson->title_en ?: $lesson->title);
        $prefix = $edition === 'teacher' ? 'lesson-teacher-edition' : 'lesson-workbook';

        return $prefix.'-'.($slug ?: $lesson->id).'.pdf';
    }

    private function lessonPdfUrl(Lesson $lesson): ?string
    {
        if (! $lesson->content_pdf_enabled) {
            return null;
        }

        if ($lesson->content_pdf_url && Str::startsWith($lesson->content_pdf_url, ['http://', 'https://'])) {
            return $lesson->content_pdf_url;
        }

        $media = $lesson->contentPdfMedia;
        if (! $media || $media->status !== 'published') {
            return null;
        }

        return URL::temporarySignedRoute('media.stream', now()->addMinutes(15), [
            'media' => $media->id,
            'download' => 0,
        ]);
    }
}
