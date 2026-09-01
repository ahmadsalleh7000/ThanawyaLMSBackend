<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertCourseRequest;
use App\Models\AcademicSubject;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\InstructorProfile;
use App\Enums\CourseScope;
use App\Services\AuditService;
use App\Services\CourseLifecycleService;
use App\Services\CourseReadinessService;
use App\Services\CourseVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminCourseController extends Controller
{
    public function index(Request $request): View
    {
        $filters = validator($request->only(['status', 'scope', 'year', 'grade', 'semester', 'category', 'sort', 'view']), [
            'status' => ['nullable', Rule::in(['published', 'draft', 'archived'])],
            'scope' => ['nullable', Rule::enum(CourseScope::class)],
            'year' => ['nullable', 'integer', 'exists:academic_years,id'],
            'grade' => ['nullable', Rule::in(['grade-1', 'grade-2', 'grade-3'])],
            'semester' => ['nullable', Rule::in(['semester-1', 'semester-2'])],
            'category' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['latest', 'title', 'content', 'status', 'enrollments'])],
            'view' => ['nullable', Rule::in(['table', 'grid'])],
        ])->validate();
        $query = Course::with([
            'academicSubject.term.academicYear', 'academicSubject.term.grade', 'academicSubject.subject',
            'units' => fn ($units) => $units->select(['id', 'course_id'])->withCount('trainings'),
            'lessons' => fn ($lessons) => $lessons->select(['id', 'course_id', 'status', 'position'])->withCount(['mediaAssets', 'trainings']),
        ])->withCount([
            'units', 'lessons', 'enrollments', 'trainings',
            'lessons as published_lessons_count' => fn ($lessons) => $lessons->where('status', 'published'),
            'lessons as preview_lessons_count' => fn ($lessons) => $lessons->where('is_preview', true),
        ])->withExists('assessment');
        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(fn ($inner) => $inner->where('title', 'like', '%'.$search.'%')->orWhere('title_en', 'like', '%'.$search.'%')->orWhere('id', 'like', '%'.$search.'%'));
        }
        $query->when($filters['status'] ?? null, function ($query, string $status): void {
            match ($status) {
                'published' => $query->where('is_published', true)->whereNull('archived_at'),
                'draft' => $query->where('is_published', false)->whereNull('archived_at'),
                'archived' => $query->whereNotNull('archived_at'),
            };
        })->when($filters['scope'] ?? null, fn ($query, $scope) => $query->where('scope', $scope))
            ->when($filters['year'] ?? null, fn ($query, $year) => $query->whereHas('academicSubject.term', fn ($term) => $term->where('academic_year_id', $year)))
            ->when($filters['grade'] ?? null, fn ($query, $grade) => $query->academic()->where('grade', $grade))
            ->when($filters['semester'] ?? null, fn ($query, $semester) => $query->where('semester', $semester))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category));

        match ($filters['sort'] ?? 'latest') {
            'title' => $query->orderBy('title'),
            'content' => $query->orderByDesc('lessons_count')->orderByDesc('units_count'),
            'status' => $query->orderByRaw('archived_at is null desc')->orderByDesc('is_published')->latest(),
            'enrollments' => $query->orderByDesc('enrollments_count'),
            default => $query->latest(),
        };

        $courses = $query->paginate(12)->withQueryString();
        $courses->getCollection()->each(function (Course $course): void {
            $course->setAttribute('resources_count', $course->lessons->sum('media_assets_count'));
            $course->setAttribute('content_trainings_count', $course->trainings_count + $course->units->sum('trainings_count') + $course->lessons->sum('trainings_count'));
            $checks = [filled($course->title) && filled($course->title_en), filled($course->description) && filled($course->description_en), $course->units_count > 0, $course->lessons_count > 0, $course->published_lessons_count > 0];
            $course->setAttribute('list_readiness_score', (int) round(collect($checks)->filter()->count() / count($checks) * 100));
        });

        return view('admin.courses.index', [
            'courses' => $courses,
            'search' => $search ?? '',
            'display' => $filters['view'] ?? 'table',
            'categories' => Course::query()->select('category')->distinct()->orderBy('category')->pluck('category'),
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'name_ar']),
            'stats' => [
                'total' => Course::count(),
                'published' => Course::available()->count(),
                'drafts' => Course::notArchived()->where('is_published', false)->count(),
                'archived' => Course::whereNotNull('archived_at')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $course = new Course;
        $course->scope = request('scope', 'academic');
        $course->academic_subject_id = request()->integer('academic_subject_id') ?: null;

        return view('admin.courses.form', ['course' => $course, 'instructors' => $this->instructors(), 'academicSubjects' => $this->academicSubjects()]);
    }

    public function store(UpsertCourseRequest $request, AuditService $audit): RedirectResponse
    {
        $course = Course::create($this->courseData($request) + ['is_published' => false]);
        $audit->record($request, 'course.created', $course);

        return redirect()->route('admin.courses.edit', $course)->with('success', 'تم إنشاء مسودة الدورة.');
    }

    public function edit(Course $course): View
    {
        return view('admin.courses.form', ['course' => $course->load(['lessons.mediaAssets', 'assessment.questions', 'academicSubject.term.grade', 'academicSubject.subject']), 'instructors' => $this->instructors(true), 'academicSubjects' => $this->academicSubjects()]);
    }

    public function readiness(Course $course, CourseReadinessService $readiness): View
    {
        return view('admin.courses.readiness', ['course' => $course, 'report' => $readiness->inspect($course)]);
    }

    public function preview(Course $course, CourseReadinessService $readiness): View
    {
        $course->load(['lessons.mediaAssets', 'assessment.questions']);

        return view('admin.courses.preview', ['course' => $course, 'report' => $readiness->inspect($course)]);
    }

    public function update(UpsertCourseRequest $request, Course $course, AuditService $audit, CourseVersionService $versions): RedirectResponse
    {
        abort_if($course->archived_at, 409, 'Archived courses cannot be edited.');
        $this->guardScopeChange($course, $request->validated('scope'));
        $before = $course->attributesToArray();
        $versions->capture($course, $request->user(), 'before_update');
        $course->update($this->courseData($request));
        $audit->record($request, 'course.updated', $course, $before);

        return back()->with('success', 'تم حفظ بيانات الدورة.');
    }

    private function guardScopeChange(Course $course, ?string $scope): void
    {
        if (! $scope || $scope === ($course->scope?->value ?? CourseScope::Academic->value)) {
            return;
        }
        if ($course->is_published || $course->enrollments()->exists()) {
            throw ValidationException::withMessages(['scope' => ['ألغِ نشر الدورة وتأكد من عدم وجود تسجيلات قبل تغيير جمهورها.']]);
        }
    }

    public function state(Request $request, Course $course, string $action, AuditService $audit, CourseLifecycleService $lifecycle, CourseVersionService $versions): RedirectResponse
    {
        $before = $course->attributesToArray();
        $versions->capture($course, $request->user(), 'before_'.$action);
        $lifecycle->transition($course, $action);
        $event = ['publish' => 'published', 'unpublish' => 'unpublished', 'archive' => 'archived', 'restore' => 'restored'][$action];
        $audit->record($request, "course.{$event}", $course, $before);

        return back()->with('success', 'تم تحديث حالة الدورة.');
    }

    private function courseData(UpsertCourseRequest $request): array
    {
        $data = $request->validated();
        if (($data['scope'] ?? null) === CourseScope::General->value) {
            $data['academic_subject_id'] = null;
            $data['grade'] = null;
            $data['semester'] = null;

            return $this->applyInstructor($data);
        }
        if ($academicSubject = AcademicSubject::with(['term.grade', 'subject'])->find($data['academic_subject_id'] ?? null)) {
            $data['grade'] = $academicSubject->term->grade->code;
            $data['semester'] = $academicSubject->term->code;
            $data['category'] = $academicSubject->subject->name_en;
        }
        return $this->applyInstructor($data);
    }

    private function applyInstructor(array $data): array
    {
        if ($profile = InstructorProfile::find($data['instructor_profile_id'] ?? null)) {
            $data['instructor'] = $profile->name;
            $data['instructor_en'] = $profile->name_en;
        }

        return $data;
    }

    private function instructors(bool $includeInactive = false)
    {
        return InstructorProfile::query()->when(! $includeInactive, fn ($query) => $query->where('is_active', true))->orderBy('name')->get();
    }

    private function academicSubjects()
    {
        return AcademicSubject::where('is_active', true)->with(['term.academicYear', 'term.grade', 'subject'])
            ->whereHas('term', fn ($query) => $query->active())->get()
            ->sortBy(fn ($item) => $item->term->academicYear->starts_on.'-'.$item->term->grade->display_order.'-'.$item->term->display_order.'-'.$item->display_order);
    }
}
