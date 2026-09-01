<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\AuditService;
use App\Services\EnrollmentLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminEnrollmentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $search = $request->string('search')->trim()->toString();
        $enrollments = Enrollment::query()->with(['user:id,name,email', 'course:id,title,title_en', 'events.actor:id,name'])
            ->when(in_array($status, ['active', 'suspended', 'cancelled', 'expired'], true), function ($query) use ($status): void {
                $status === 'expired'
                    ? $query->where('status', 'active')->where('access_expires_at', '<=', now())
                    : $query->where('status', $status);
            })
            ->when($search, fn ($query) => $query->where(fn ($inner) => $inner->whereHas('user', fn ($user) => $user->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))->orWhereHas('course', fn ($course) => $course->where('title', 'like', '%'.$search.'%')->orWhere('title_en', 'like', '%'.$search.'%'))))
            ->latest('enrolled_at')->paginate(15)->withQueryString();

        return view('admin.enrollments.index', [
            'enrollments' => $enrollments, 'search' => $search,
            'students' => User::students()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
            'courses' => Course::notArchived()->orderBy('title')->get(['id', 'title']),
            'counts' => ['active' => Enrollment::active()->count(), 'suspended' => Enrollment::where('status', 'suspended')->count(), 'expired' => Enrollment::where('status', 'active')->where('access_expires_at', '<=', now())->count()],
        ]);
    }

    public function store(Request $request, EnrollmentLifecycleService $lifecycle, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', Rule::exists('users', 'id')->where('role', 'student')], 'course_id' => ['required', 'exists:courses,id'], 'access_expires_at' => ['nullable', 'date', 'after:now'], 'reason' => ['nullable', 'string', 'max:2000']]);
        $enrollment = $lifecycle->enroll(User::findOrFail($data['user_id']), Course::findOrFail($data['course_id']), 'admin', isset($data['access_expires_at']) ? CarbonImmutable::parse($data['access_expires_at']) : null, $request->user(), $data['reason'] ?? null);
        $audit->record($request, 'enrollment.managed', $enrollment);

        return back()->with('success', 'تم تفعيل تسجيل الطالب.');
    }

    public function action(Request $request, Enrollment $enrollment, EnrollmentLifecycleService $lifecycle, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['suspend', 'activate', 'cancel', 'extend'])], 'reason' => ['nullable', 'required_unless:action,activate', 'string', 'min:5', 'max:2000'], 'access_expires_at' => ['nullable', 'required_if:action,extend', 'date', 'after:now']]);
        $before = $enrollment->attributesToArray();
        match ($data['action']) {
            'suspend' => $lifecycle->suspend($enrollment, $request->user(), $data['reason']),
            'activate' => $lifecycle->activate($enrollment, $request->user(), $data['reason'] ?? null),
            'cancel' => $lifecycle->cancel($enrollment, $request->user(), $data['reason']),
            'extend' => $lifecycle->extend($enrollment, $request->user(), CarbonImmutable::parse($data['access_expires_at']), $data['reason']),
        };
        $audit->record($request, 'enrollment.'.$data['action'], $enrollment->refresh(), $before);

        return back()->with('success', 'تم تحديث صلاحية التسجيل.');
    }

    public function transfer(Request $request, Enrollment $enrollment, EnrollmentLifecycleService $lifecycle, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['course_id' => ['required', 'different:current_course', 'exists:courses,id'], 'current_course' => ['required', 'in:'.$enrollment->course_id], 'reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $new = $lifecycle->transfer($enrollment, Course::findOrFail($data['course_id']), $request->user(), $data['reason']);
        $audit->record($request, 'enrollment.transferred', $new);

        return back()->with('success', 'تم نقل الطالب إلى الدورة الجديدة.');
    }

    public function import(Request $request, EnrollmentLifecycleService $lifecycle): RedirectResponse
    {
        $file = $request->validate(['csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048']])['csv'];
        $handle = fopen($file->getRealPath(), 'r');
        $header = array_map(fn ($value) => strtolower(trim((string) $value)), fgetcsv($handle) ?: []);
        abort_unless($header === ['email', 'course_id', 'expires_at'], 422, 'CSV header must be: email,course_id,expires_at');
        $imported = 0;
        while (($row = fgetcsv($handle)) !== false && $imported < 2000) {
            if (count($row) !== 3 || ! ($student = User::students()->where('email', trim($row[0]))->first()) || ! ($course = Course::find(trim($row[1])))) {
                continue;
            }
            $expires = trim($row[2]) !== '' ? CarbonImmutable::parse(trim($row[2])) : null;
            $lifecycle->enroll($student, $course, 'csv', $expires, $request->user(), 'Bulk CSV import');
            $imported++;
        }
        fclose($handle);

        return back()->with('success', "تم استيراد {$imported} تسجيلات.");
    }
}
