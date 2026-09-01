<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertLessonRequest;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuestionBankQuiz;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminLessonController extends Controller
{
    public function edit(Course $course, Lesson $lesson): View
    {
        $this->ensureLesson($course, $lesson);

        return view('admin.courses.lesson', [
            'course' => $course->load('academicSubject.units'),
            'lesson' => $lesson->load(['mediaAssets', 'trainings.questions', 'trainings.quiz.questions', 'sections']),
            'questionQuizzes' => QuestionBankQuiz::where('is_active', true)->withCount('questions')->orderBy('name_ar')->get(['id', 'name_ar', 'name_en', 'type', 'passing_score', 'is_required']),
        ]);
    }

    public function store(UpsertLessonRequest $request, Course $course, AuditService $audit): RedirectResponse
    {
        abort_if($course->archived_at, 409);
        $lesson = $course->lessons()->create($request->validated() + ['is_preview' => false, 'status' => 'draft']);
        $audit->record($request, 'lesson.created', $lesson);

        return back()->with('success', 'تمت إضافة الدرس.');
    }

    public function destroy(Request $request, Course $course, Lesson $lesson, AuditService $audit): RedirectResponse
    {
        abort_unless($lesson->course_id === $course->id, 404);
        abort_if($course->is_published, 409, 'Unpublish before deleting lessons.');
        $before = $lesson->attributesToArray();
        $audit->record($request, 'lesson.deleted', $lesson, $before);
        $lesson->delete();

        return back()->with('success', 'تم حذف الدرس.');
    }

    public function update(UpsertLessonRequest $request, Course $course, Lesson $lesson, AuditService $audit): RedirectResponse
    {
        $this->ensureLesson($course, $lesson);
        abort_if($course->archived_at, 409);
        $before = $lesson->attributesToArray();
        $payload = $request->safe()->except(['transcript_text', 'transcript_en_text']);
        foreach (['is_preview', 'content_pdf_enabled', 'workbook_enabled', 'allow_print', 'allow_pdf_download', 'include_workbook_cover', 'include_workbook_info', 'include_workbook_toc', 'include_workbook_answer_key'] as $checkbox) {
            if ($request->has($checkbox)) {
                $payload[$checkbox] = $request->boolean($checkbox);
            }
        }

        $this->syncContentPdfTitleWhenSelectionChanges($request, $lesson, $payload);
        $payload = $this->normalizeUtf8Payload($payload);

        $lesson->update($payload);
        $audit->record($request, 'lesson.updated', $lesson, $before);

        return back()->with('success', 'تم تحديث بيانات الدرس.');
    }

    public function move(Request $request, Course $course, Lesson $lesson, string $direction, AuditService $audit): RedirectResponse
    {
        $this->ensureLesson($course, $lesson);
        abort_unless(in_array($direction, ['up', 'down'], true), 404);
        abort_if($course->archived_at, 409);
        $operator = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'desc' : 'asc';
        $adjacent = $course->lessons()->where('position', $operator, $lesson->position)->orderBy('position', $order)->first();
        if (! $adjacent) {
            return back();
        }
        $before = ['lesson' => $lesson->attributesToArray(), 'adjacent' => $adjacent->attributesToArray()];
        $adjacentPosition = $adjacent->position;
        DB::transaction(function () use ($lesson, $adjacent, $adjacentPosition): void {
            $position = $lesson->position;
            $lesson->update(['position' => 1000000]);
            $adjacent->update(['position' => $position]);
            $lesson->update(['position' => $adjacentPosition]);
        });
        $audit->record($request, 'lessons.reordered', $course, $before);

        return back()->with('success', 'تم تحديث ترتيب الدروس.');
    }

    private function ensureLesson(Course $course, Lesson $lesson): void
    {
        abort_unless($lesson->course_id === $course->id, 404);
    }

    /**
     * When admins switch to a different uploaded PDF, keep the displayed
     * reader title aligned unless they intentionally typed a new custom title.
     *
     * @param  array<string, mixed>  $payload
     */
    private function syncContentPdfTitleWhenSelectionChanges(Request $request, Lesson $lesson, array &$payload): void
    {
        if (! $request->has('content_pdf_media_id') || blank($payload['content_pdf_media_id'] ?? null)) {
            return;
        }

        if ((int) $payload['content_pdf_media_id'] === (int) $lesson->content_pdf_media_id) {
            return;
        }

        $asset = $lesson->mediaAssets()
            ->whereKey($payload['content_pdf_media_id'])
            ->where('mime_type', 'application/pdf')
            ->first();

        if (! $asset) {
            return;
        }

        $submittedTitle = $payload['content_pdf_title'] ?? null;
        $keptPreviousTitle = $request->has('content_pdf_title')
            && filled($lesson->content_pdf_title)
            && $submittedTitle === $lesson->content_pdf_title;

        if (! $request->has('content_pdf_title') || blank($submittedTitle) || $keptPreviousTitle) {
            $payload['content_pdf_title'] = pathinfo($asset->original_name, PATHINFO_FILENAME);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeUtf8Payload(array $payload): array
    {
        return array_map(fn ($value) => $this->normalizeUtf8Value($value), $payload);
    }

    private function normalizeUtf8Value(mixed $value): mixed
    {
        if (is_string($value)) {
            if (mb_check_encoding($value, 'UTF-8')) {
                return $value;
            }

            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

            return $converted === false ? mb_convert_encoding($value, 'UTF-8', 'UTF-8') : $converted;
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->normalizeUtf8Value($item), $value);
        }

        return $value;
    }
}
