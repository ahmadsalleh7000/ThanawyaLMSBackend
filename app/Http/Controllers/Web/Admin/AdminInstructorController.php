<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\InstructorProfile;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminInstructorController extends Controller
{
    public function index(Request $request): View
    {
        $filters = validator($request->only(['status', 'workload', 'profile', 'sort', 'view']), [
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'workload' => ['nullable', Rule::in(['assigned', 'unassigned'])],
            'profile' => ['nullable', Rule::in(['complete', 'incomplete'])],
            'sort' => ['nullable', Rule::in(['latest', 'name', 'courses'])],
            'view' => ['nullable', Rule::in(['table', 'grid'])],
        ])->validate();
        $search = $request->string('search')->trim()->toString();
        $query = InstructorProfile::query()
            ->with(['courses' => fn ($query) => $query->withCount('enrollments')->withAvg(['reviews as published_rating' => fn ($review) => $review->published()], 'rating')])
            ->withCount('courses')
            ->when($search, fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', '%'.$search.'%')->orWhere('name_en', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('is_active', $status === 'active'))
            ->when(($filters['workload'] ?? null) === 'assigned', fn ($query) => $query->has('courses'))
            ->when(($filters['workload'] ?? null) === 'unassigned', fn ($query) => $query->doesntHave('courses'))
            ->when(($filters['profile'] ?? null) === 'complete', fn ($query) => $query->whereNotNull('headline')->whereNotNull('headline_en')->whereNotNull('bio')->whereNotNull('bio_en')->whereNotNull('email'))
            ->when(($filters['profile'] ?? null) === 'incomplete', fn ($query) => $query->where(fn ($inner) => $inner->whereNull('headline')->orWhereNull('headline_en')->orWhereNull('bio')->orWhereNull('bio_en')->orWhereNull('email')));

        match ($filters['sort'] ?? 'latest') {
            'name' => $query->orderBy('name'),
            'courses' => $query->orderByDesc('courses_count'),
            default => $query->latest(),
        };

        $instructors = $query->paginate(12)->withQueryString();
        $instructors->getCollection()->each(function (InstructorProfile $instructor): void {
            $fields = [$instructor->name, $instructor->name_en, $instructor->headline, $instructor->headline_en, $instructor->bio, $instructor->bio_en, $instructor->email];
            $instructor->setAttribute('profile_completion', (int) round(collect($fields)->filter(fn ($value) => filled($value))->count() / count($fields) * 100));
        });

        return view('admin.instructors.index', [
            'instructors' => $instructors,
            'search' => $search,
            'display' => $filters['view'] ?? 'table',
            'stats' => [
                'total' => InstructorProfile::count(),
                'active' => InstructorProfile::where('is_active', true)->count(),
                'assigned' => InstructorProfile::has('courses')->count(),
                'unassigned' => InstructorProfile::doesntHave('courses')->count(),
            ],
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $instructor = InstructorProfile::create($this->validated($request));
        $audit->record($request, 'instructor.created', $instructor);

        return back()->with('success', 'تم إنشاء ملف المدرس.');
    }

    public function update(Request $request, InstructorProfile $instructor, AuditService $audit): RedirectResponse
    {
        $before = $instructor->attributesToArray();
        $instructor->update($this->validated($request, $instructor));
        $instructor->courses()->update(['instructor' => $instructor->name, 'instructor_en' => $instructor->name_en]);
        $audit->record($request, 'instructor.updated', $instructor, $before);

        return back()->with('success', 'تم تحديث ملف المدرس والدورات المرتبطة.');
    }

    public function toggle(Request $request, InstructorProfile $instructor, AuditService $audit): RedirectResponse
    {
        $before = $instructor->attributesToArray();
        $instructor->update(['is_active' => ! $instructor->is_active]);
        $audit->record($request, $instructor->is_active ? 'instructor.activated' : 'instructor.deactivated', $instructor, $before);

        return back()->with('success', 'تم تحديث حالة المدرس.');
    }

    public function assign(Request $request, Course $course, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['instructor_profile_id' => ['required', 'integer', 'exists:instructor_profiles,id']]);
        $instructor = InstructorProfile::findOrFail($data['instructor_profile_id']);
        $before = $course->attributesToArray();
        $course->update(['instructor_profile_id' => $instructor->id, 'instructor' => $instructor->name, 'instructor_en' => $instructor->name_en]);
        $audit->record($request, 'course.instructor_assigned', $course, $before);

        return back()->with('success', 'تم ربط المدرس بالدورة.');
    }

    private function validated(Request $request, ?InstructorProfile $instructor = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'headline_en' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'bio_en' => ['nullable', 'string', 'max:5000'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('instructor_profiles')->ignore($instructor)],
        ]);
    }
}
