<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertLessonSectionRequest;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonSection;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminLessonSectionController extends Controller
{
    public function store(UpsertLessonSectionRequest $request, Course $course, Lesson $lesson, AuditService $audit): RedirectResponse
    {
        $this->ensureLesson($course, $lesson);
        abort_if($course->archived_at, 409);

        $payload = $this->validatedPayload($request, $lesson);
        $section = $lesson->sections()->create($payload + [
            'sort_order' => ((int) $lesson->sections()->max('sort_order')) + 1,
        ]);
        $audit->record($request, 'lesson_section.created', $section);

        return back()->with('success', 'تمت إضافة كتلة المحتوى.');
    }

    public function update(UpsertLessonSectionRequest $request, Course $course, Lesson $lesson, LessonSection $section, AuditService $audit): RedirectResponse
    {
        $this->ensureSection($course, $lesson, $section);
        abort_if($course->archived_at, 409);

        $before = $section->attributesToArray();
        $section->update($this->validatedPayload($request, $lesson));
        $audit->record($request, 'lesson_section.updated', $section, $before);

        return back()->with('success', 'تم تحديث كتلة المحتوى.');
    }

    public function duplicate(Request $request, Course $course, Lesson $lesson, LessonSection $section, AuditService $audit): RedirectResponse
    {
        $this->ensureSection($course, $lesson, $section);
        abort_if($course->archived_at, 409);

        $copy = $lesson->sections()->create([
            'type' => $section->type,
            'title' => trim(($section->title ?: 'كتلة محتوى').' - نسخة'),
            'content' => $section->content,
            'settings' => $section->settings,
            'sort_order' => ((int) $lesson->sections()->max('sort_order')) + 1,
        ]);
        $audit->record($request, 'lesson_section.duplicated', $copy);

        return back()->with('success', 'تم نسخ كتلة المحتوى.');
    }

    public function move(Request $request, Course $course, Lesson $lesson, LessonSection $section, string $direction, AuditService $audit): RedirectResponse
    {
        $this->ensureSection($course, $lesson, $section);
        abort_unless(in_array($direction, ['up', 'down'], true), 404);
        abort_if($course->archived_at, 409);

        $operator = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'desc' : 'asc';
        $adjacent = $lesson->sections()->where('sort_order', $operator, $section->sort_order)->orderBy('sort_order', $order)->first();
        if (! $adjacent) {
            return back();
        }

        DB::transaction(function () use ($section, $adjacent): void {
            $currentOrder = $section->sort_order;
            $section->update(['sort_order' => $adjacent->sort_order]);
            $adjacent->update(['sort_order' => $currentOrder]);
        });
        $audit->record($request, 'lesson_section.reordered', $section);

        return back()->with('success', 'تم تحديث ترتيب المحتوى.');
    }

    public function destroy(Request $request, Course $course, Lesson $lesson, LessonSection $section, AuditService $audit): RedirectResponse
    {
        $this->ensureSection($course, $lesson, $section);
        abort_if($course->archived_at, 409);

        $before = $section->attributesToArray();
        $audit->record($request, 'lesson_section.deleted', $section, $before);
        $section->delete();

        return back()->with('success', 'تم حذف كتلة المحتوى.');
    }

    private function ensureSection(Course $course, Lesson $lesson, LessonSection $section): void
    {
        $this->ensureLesson($course, $lesson);
        abort_unless($section->lesson_id === $lesson->id, 404);
    }

    private function ensureLesson(Course $course, Lesson $lesson): void
    {
        abort_unless($lesson->course_id === $course->id, 404);
    }

    private function validatedPayload(UpsertLessonSectionRequest $request, Lesson $lesson): array
    {
        $payload = $request->validated();
        $imageMediaId = data_get($payload, 'content.image.media_asset_id');
        abort_if($imageMediaId && ! $lesson->mediaAssets()->whereKey($imageMediaId)->exists(), 422, 'The selected image must belong to this lesson.');

        return $payload;
    }
}
