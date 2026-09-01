<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseVersion;
use App\Models\ScheduledCourseAction;
use App\Services\AuditService;
use App\Services\CourseDuplicationService;
use App\Services\CourseVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminCourseOperationsController extends Controller
{
    public function index(Course $course): View
    {
        return view('admin.courses.operations', [
            'course' => $course,
            'versions' => $course->versions()->with('creator:id,name')->paginate(15, ['*'], 'versions'),
            'scheduledActions' => $course->scheduledActions()->with('creator:id,name')->limit(20)->get(),
        ]);
    }

    public function duplicate(Request $request, Course $course, CourseDuplicationService $duplicates, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['id' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:courses,id'], 'title' => ['required', 'string', 'max:255'], 'title_en' => ['required', 'string', 'max:255']]);
        $copy = $duplicates->duplicate($course, $data['id'], $data['title'], $data['title_en']);
        $audit->record($request, 'course.duplicated', $copy);

        return redirect()->route('admin.courses.edit', $copy)->with('success', 'تم إنشاء نسخة كاملة كمسودة دون بيانات الطلاب.');
    }

    public function snapshot(Request $request, Course $course, CourseVersionService $versions, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['label' => ['nullable', 'string', 'max:255']]);
        $version = $versions->capture($course, $request->user(), 'manual', $data['label'] ?? null);
        $audit->record($request, 'course.version_created', $version);

        return back()->with('success', 'تم حفظ نسخة كاملة من حالة الدورة الحالية.');
    }

    public function restore(Request $request, Course $course, CourseVersion $version, CourseVersionService $versions, AuditService $audit): RedirectResponse
    {
        $before = $course->attributesToArray();
        $versions->restoreMetadata($course, $version, $request->user());
        $audit->record($request, 'course.version_restored', $course->refresh(), $before);

        return back()->with('success', 'تمت استعادة بيانات الدورة، وحُفظت نسخة أمان تلقائياً.');
    }

    public function schedule(Request $request, Course $course, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['publish', 'unpublish'])], 'run_at' => ['required', 'date', 'after:now']]);
        $scheduled = $course->scheduledActions()->create($data + ['created_by' => $request->user()->id, 'status' => 'pending']);
        $audit->record($request, 'course.action_scheduled', $scheduled);

        return back()->with('success', 'تمت جدولة تغيير حالة الدورة.');
    }

    public function cancel(Request $request, ScheduledCourseAction $scheduled, AuditService $audit): RedirectResponse
    {
        abort_unless($scheduled->status === 'pending', 409);
        $before = $scheduled->attributesToArray();
        $scheduled->update(['status' => 'cancelled', 'completed_at' => now()]);
        $audit->record($request, 'course.schedule_cancelled', $scheduled, $before);

        return back()->with('success', 'تم إلغاء الإجراء المجدول.');
    }
}
