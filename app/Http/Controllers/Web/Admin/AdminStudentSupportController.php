<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentSupportActivity;
use App\Models\StudentSupportCase;
use App\Models\User;
use App\Services\AuditService;
use App\Services\StudentRiskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminStudentSupportController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $cases = StudentSupportCase::query()->with(['student:id,name,email', 'assignee:id,name'])
            ->withCount(['activities', 'activities as pending_tasks_count' => fn ($query) => $query->where('type', 'task')->whereNull('completed_at')])
            ->when(in_array($status, ['open', 'monitoring', 'resolved'], true), fn ($query) => $query->where('status', $status))
            ->when($request->string('risk')->toString(), fn ($query, $risk) => $query->where('risk_level', $risk))
            ->orderByRaw("case when risk_level = 'high' then 0 else 1 end")->latest('detected_at')->paginate(20)->withQueryString();

        return view('admin.support.index', [
            'cases' => $cases,
            'students' => User::students()->where('is_active', true)->doesntHave('supportCase')->orderBy('name')->get(['id', 'name', 'email']),
            'counts' => ['open' => StudentSupportCase::where('status', 'open')->count(), 'high' => StudentSupportCase::whereNot('status', 'resolved')->where('risk_level', 'high')->count(), 'tasks' => StudentSupportActivity::where('type', 'task')->whereNull('completed_at')->count()],
        ]);
    }

    public function show(StudentSupportCase $case): View
    {
        return view('admin.support.show', [
            'case' => $case->load(['student.linkedParents:id,name,email', 'student.enrollments.course:id,title', 'assignee:id,name', 'activities.creator:id,name']),
            'staff' => User::where('role', User::ROLE_ADMIN)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'admin_role']),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['student_id' => ['required', Rule::exists('users', 'id')->where('role', 'student')], 'risk_level' => ['required', Rule::in(['medium', 'high'])], 'reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $case = StudentSupportCase::create(['student_id' => $data['student_id'], 'status' => 'open', 'risk_level' => $data['risk_level'], 'risk_score' => $data['risk_level'] === 'high' ? 80 : 50, 'risk_signals' => [['key' => 'manual', 'label' => $data['reason']]], 'detected_at' => now()]);
        $case->activities()->create(['created_by' => $request->user()->id, 'type' => 'note', 'body' => $data['reason']]);
        $audit->record($request, 'support_case.created', $case);

        return redirect()->route('admin.support.show', $case)->with('success', 'تم فتح حالة متابعة للطالب.');
    }

    public function scan(Request $request, StudentRiskService $risk, AuditService $audit): RedirectResponse
    {
        $count = $risk->scan();
        $audit->record($request, 'support_risk.scan', $request->user());

        return back()->with('success', "تم تحديث {$count} حالات تحتاج متابعة.");
    }

    public function update(Request $request, StudentSupportCase $case, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'monitoring', 'resolved'])], 'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('role', 'admin')], 'next_follow_up_at' => ['nullable', 'date', 'after:now']]);
        $before = $case->attributesToArray();
        $case->update($data + ['resolved_at' => $data['status'] === 'resolved' ? now() : null]);
        $audit->record($request, 'support_case.updated', $case, $before);

        return back()->with('success', 'تم تحديث حالة المتابعة.');
    }

    public function activity(Request $request, StudentSupportCase $case, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(['note', 'contact', 'task', 'parent_escalation'])], 'channel' => ['nullable', Rule::in(['phone', 'email', 'whatsapp', 'in_app', 'meeting'])], 'body' => ['required', 'string', 'min:5', 'max:5000'], 'outcome' => ['nullable', 'string', 'max:1000'], 'due_at' => ['nullable', 'required_if:type,task', 'date', 'after:now']]);
        $activity = $case->activities()->create($data + ['created_by' => $request->user()->id]);
        if ($data['type'] === 'parent_escalation') {
            foreach ($case->student->linkedParents as $parent) {
                $parent->userNotifications()->create(['type' => 'student_support', 'title' => 'Student follow-up requested', 'title_ar' => 'مطلوب متابعة الطالب', 'message' => $data['body'], 'message_ar' => $data['body'], 'action_url' => '/parent/students/'.$case->student_id]);
            }
        }
        $audit->record($request, 'support_activity.created', $activity);

        return back()->with('success', $data['type'] === 'parent_escalation' ? 'تم تسجيل التصعيد وإخطار أولياء الأمور المرتبطين.' : 'تم تسجيل نشاط المتابعة.');
    }

    public function complete(Request $request, StudentSupportActivity $activity, AuditService $audit): RedirectResponse
    {
        abort_unless($activity->type === 'task' && ! $activity->completed_at, 409);
        $before = $activity->attributesToArray();
        $activity->update(['completed_at' => now()]);
        $audit->record($request, 'support_task.completed', $activity, $before);

        return back()->with('success', 'تم إكمال مهمة المتابعة.');
    }
}
