<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Unit;
use App\Services\LessonWorkbookService;
use App\Services\UnitWorkbookService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LessonWorkbookController extends Controller
{
    public function __construct(
        private readonly LessonWorkbookService $workbooks,
        private readonly UnitWorkbookService $unitWorkbooks,
    ) {}

    public function adminPreview(Course $course, Lesson $lesson): View
    {
        abort_unless($lesson->course_id === $course->id, 404);

        return view('workbooks.lesson', $this->workbooks->prepare($lesson) + [
            'adminPreview' => true,
        ]);
    }

    public function adminDownload(Course $course, Lesson $lesson): BinaryFileResponse
    {
        abort_unless($lesson->course_id === $course->id, 404);

        $pdf = $this->workbooks->generatePdf($lesson);

        return response()
            ->download($pdf['path'], $pdf['filename'], [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ])
            ->deleteFileAfterSend();
    }

    public function teacherPreview(Course $course, Lesson $lesson): View
    {
        abort_unless($lesson->course_id === $course->id, 404);

        return view('workbooks.lesson', $this->workbooks->prepare($lesson, 'teacher') + [
            'adminPreview' => true,
        ]);
    }

    public function teacherDownload(Course $course, Lesson $lesson): BinaryFileResponse
    {
        abort_unless($lesson->course_id === $course->id, 404);

        $pdf = $this->workbooks->generatePdf($lesson, 'teacher');

        return response()
            ->download($pdf['path'], $pdf['filename'], [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ])
            ->deleteFileAfterSend();
    }

    public function unitPreview(Course $course, Unit $unit): View
    {
        $this->ensureUnit($course, $unit);

        return view('workbooks.unit', $this->unitWorkbooks->prepare($unit) + [
            'adminPreview' => true,
        ]);
    }

    public function unitDownload(Course $course, Unit $unit): BinaryFileResponse
    {
        $this->ensureUnit($course, $unit);

        $pdf = $this->unitWorkbooks->generatePdf($unit);

        return response()
            ->download($pdf['path'], $pdf['filename'], [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ])
            ->deleteFileAfterSend();
    }

    public function unitTeacherPreview(Course $course, Unit $unit): View
    {
        $this->ensureUnit($course, $unit);

        return view('workbooks.unit', $this->unitWorkbooks->prepare($unit, 'teacher') + [
            'adminPreview' => true,
        ]);
    }

    public function unitTeacherDownload(Course $course, Unit $unit): BinaryFileResponse
    {
        $this->ensureUnit($course, $unit);

        $pdf = $this->unitWorkbooks->generatePdf($unit, 'teacher');

        return response()
            ->download($pdf['path'], $pdf['filename'], [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ])
            ->deleteFileAfterSend();
    }

    private function ensureUnit(Course $course, Unit $unit): void
    {
        abort_unless($unit->course_id === $course->id, 404);
    }
}
