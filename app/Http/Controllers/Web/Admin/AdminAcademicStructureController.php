<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicGrade;
use App\Models\AcademicSubject;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Subject;
use App\Models\SubscriptionPlan;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminAcademicStructureController extends Controller
{
    public function index(): View
    {
        return view('admin.academics.index', [
            'years' => AcademicYear::withCount('terms')->latest('starts_on')->get(),
            'grades' => AcademicGrade::withCount('terms')->orderBy('display_order')->get(),
            'terms' => AcademicTerm::with(['academicYear', 'grade'])->withCount('academicSubjects')->orderByDesc('academic_year_id')->orderBy('display_order')->get(),
            'subjects' => Subject::withCount('offerings')->orderBy('name_ar')->get(),
            'offerings' => AcademicSubject::with(['term.academicYear', 'term.grade', 'subject', 'courses'])->withCount(['courses', 'units'])->latest()->get(),
            'plans' => SubscriptionPlan::with('academicTerms.grade')->orderBy('display_order')->get(),
            'unassignedCourses' => Course::whereNull('academic_subject_id')->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function storeYear(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:30', 'unique:academic_years'], 'name_ar' => ['required', 'string', 'max:150'], 'name_en' => ['required', 'string', 'max:150'], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after:starts_on']]);
        $year = AcademicYear::create($data + ['is_active' => false]);
        $audit->record($request, 'academic_year.created', $year);

        return back()->with('success', 'تمت إضافة العام الدراسي كمسودة.');
    }

    public function storeGrade(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/', 'unique:academic_grades'], 'name_ar' => ['required', 'string', 'max:150'], 'name_en' => ['required', 'string', 'max:150'], 'display_order' => ['required', 'integer', 'min:1', 'max:100']]);
        $grade = AcademicGrade::create($data);
        $audit->record($request, 'academic_grade.created', $grade);

        return back()->with('success', 'تمت إضافة الصف الدراسي.');
    }

    public function storeTerm(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['academic_year_id' => ['required', 'exists:academic_years,id'], 'academic_grade_id' => ['required', 'exists:academic_grades,id'], 'code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/', Rule::unique('academic_terms')->where(fn ($q) => $q->where('academic_year_id', $request->academic_year_id)->where('academic_grade_id', $request->academic_grade_id))], 'name_ar' => ['required', 'string', 'max:150'], 'name_en' => ['required', 'string', 'max:150'], 'display_order' => ['required', 'integer', 'min:1', 'max:10']]);
        $term = AcademicTerm::create($data);
        $audit->record($request, 'academic_term.created', $term);

        return back()->with('success', 'تمت إضافة الفصل الدراسي.');
    }

    public function storeSubject(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', 'unique:subjects'], 'name_ar' => ['required', 'string', 'max:150'], 'name_en' => ['required', 'string', 'max:150']]);
        $subject = Subject::create($data);
        $audit->record($request, 'subject.created', $subject);

        return back()->with('success', 'تمت إضافة المادة.');
    }

    public function storeOffering(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['academic_term_id' => ['required', 'exists:academic_terms,id'], 'subject_id' => ['required', Rule::unique('academic_subjects')->where('academic_term_id', $request->academic_term_id)], 'display_order' => ['nullable', 'integer', 'min:0', 'max:1000']]);
        $offering = AcademicSubject::create($data + ['is_active' => true]);
        $audit->record($request, 'academic_subject.created', $offering);

        return back()->with('success', 'تم ربط المادة بالسياق الدراسي.');
    }

    public function storeUnit(Request $request, AcademicSubject $offering, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('units')->where('academic_subject_id', $offering->id)], 'title_ar' => ['required', 'string', 'max:150'], 'title_en' => ['required', 'string', 'max:150'], 'position' => ['required', 'integer', 'min:1', Rule::unique('units')->where('academic_subject_id', $offering->id)]]);
        $unit = $offering->units()->create($data + ['status' => 'draft']);
        $audit->record($request, 'unit.created', $unit);

        return back()->with('success', 'تمت إضافة الوحدة كمسودة.');
    }

    public function assignCourse(Request $request, AcademicSubject $offering, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['course_id' => ['required', 'exists:courses,id']]);
        $offering->loadMissing(['term.grade', 'subject']);
        $course = Course::findOrFail($data['course_id']);
        $before = $course->attributesToArray();
        $course->update([
            'scope' => 'academic',
            'academic_subject_id' => $offering->id,
            'position' => (int) $offering->courses()->max('position') + 1,
            'grade' => $offering->term->grade->code,
            'semester' => $offering->term->code,
            'category' => $offering->subject->name_en,
        ]);
        $audit->record($request, 'course.academic_scope_assigned', $course, $before);

        return back()->with('success', 'تم ربط الدورة بالسياق الأكاديمي.');
    }

    public function syncPlan(Request $request, SubscriptionPlan $plan, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['academic_term_ids' => ['array'], 'academic_term_ids.*' => ['integer', 'exists:academic_terms,id']]);
        $before = $plan->academicTerms()->pluck('academic_terms.id')->all();
        $plan->academicTerms()->sync($data['academic_term_ids'] ?? []);
        $audit->record($request, 'subscription_plan.scope_updated', $plan, ['academic_term_ids' => $before]);

        return back()->with('success', 'تم تحديث النطاق الأكاديمي للخطة.');
    }
}
