<?php

namespace App\Services;

use App\Models\Unit;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class UnitWorkbookService
{
    public function prepare(Unit $unit, string $edition = 'student'): array
    {
        $unit->loadMissing([
            'course.academicSubject',
            'lessons' => fn ($query) => $query->orderBy('position'),
            'lessons.course.academicSubject',
            'lessons.unit',
            'lessons.contentPdfMedia.libraryItem',
            'lessons.mediaAssets.libraryItem',
            'lessons.trainings.questions',
            'lessons.sections',
        ]);

        $lessons = $unit->lessons
            ->filter(fn ($lesson) => $edition === 'teacher' || $lesson->status === 'published')
            ->values();

        return [
            'unit' => $unit,
            'course' => $unit->course,
            'lessons' => $lessons,
            'printDate' => now(),
            'brandName' => config('app.name', 'Ahmed LMS'),
            'edition' => $edition,
            'includeAnswerKey' => $edition === 'teacher',
        ];
    }

    public function generatePdf(Unit $unit, string $edition = 'student'): array
    {
        $htmlPath = tempnam(sys_get_temp_dir(), 'unit-workbook-html-').'.html';
        $pdfPath = tempnam(sys_get_temp_dir(), 'unit-workbook-pdf-').'.pdf';

        File::put($htmlPath, view('workbooks.unit', $this->prepare($unit, $edition) + ['pdfMode' => true])->render());

        $process = new Process([
            'node',
            base_path('scripts/render-workbook-pdf.mjs'),
            $htmlPath,
            $pdfPath,
        ], timeout: 60);

        $process->run();
        @unlink($htmlPath);

        if (! $process->isSuccessful() || ! File::exists($pdfPath) || File::size($pdfPath) === 0) {
            @unlink($pdfPath);
            throw new RuntimeException('Unit workbook PDF generation failed: '.$process->getErrorOutput());
        }

        return [
            'path' => $pdfPath,
            'filename' => $this->filename($unit, $edition),
        ];
    }

    public function filename(Unit $unit, string $edition = 'student'): string
    {
        $slug = Str::slug($unit->slug ?: $unit->title_en ?: $unit->title_ar);
        $prefix = $edition === 'teacher' ? 'unit-teacher-edition' : 'unit-workbook';

        return $prefix.'-'.($slug ?: $unit->id).'.pdf';
    }
}
