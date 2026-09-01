<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Course;
use App\Services\AnnouncementDeliveryService;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminAnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $announcements = Announcement::query()->with(['creator:id,name', 'course:id,title'])
            ->withCount(['notifications', 'notifications as read_count' => fn ($query) => $query->whereNotNull('read_at')])
            ->when(in_array($status, ['draft', 'scheduled', 'published', 'cancelled'], true), fn ($query) => $query->where('status', $status))
            ->latest()->paginate(15)->withQueryString();

        return view('admin.announcements.index', [
            'announcements' => $announcements,
            'courses' => Course::notArchived()->orderBy('title')->get(['id', 'title']),
            'counts' => [
                'draft' => Announcement::where('status', 'draft')->count(),
                'scheduled' => Announcement::where('status', 'scheduled')->count(),
                'published' => Announcement::where('status', 'published')->count(),
            ],
        ]);
    }

    public function store(Request $request, AnnouncementDeliveryService $delivery, AuditService $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $action = $request->validate(['action' => ['required', Rule::in(['draft', 'schedule', 'publish'])]])['action'];
        $announcement = Announcement::create($data + [
            'created_by' => $request->user()->id,
            'status' => $action === 'schedule' ? 'scheduled' : 'draft',
        ]);
        $audit->record($request, 'announcement.created', $announcement);
        if ($action === 'publish') {
            $delivery->deliver($announcement);
            $audit->record($request, 'announcement.published', $announcement->refresh());
        }

        return back()->with('success', $action === 'publish' ? 'تم نشر الإعلان وإرساله.' : ($action === 'schedule' ? 'تمت جدولة الإعلان.' : 'تم حفظ المسودة.'));
    }

    public function publish(Request $request, Announcement $announcement, AnnouncementDeliveryService $delivery, AuditService $audit): RedirectResponse
    {
        abort_unless(in_array($announcement->status, ['draft', 'scheduled'], true), 409);
        $delivery->deliver($announcement);
        $audit->record($request, 'announcement.published', $announcement->refresh());

        return back()->with('success', 'تم نشر الإعلان وإرساله دون تكرار.');
    }

    public function cancel(Request $request, Announcement $announcement, AuditService $audit): RedirectResponse
    {
        abort_unless(in_array($announcement->status, ['draft', 'scheduled'], true), 409);
        $before = $announcement->attributesToArray();
        $announcement->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $audit->record($request, 'announcement.cancelled', $announcement, $before);

        return back()->with('success', 'تم إلغاء الإعلان.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'title_ar' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'message_ar' => ['required', 'string', 'max:5000'],
            'target_type' => ['required', Rule::in(['all', 'students', 'parents', 'course', 'grade'])],
            'course_id' => ['nullable', 'required_if:target_type,course', 'exists:courses,id'],
            'grade' => ['nullable', 'required_if:target_type,grade', Rule::in(['grade-1', 'grade-2', 'grade-3'])],
            'action_url' => ['nullable', 'string', 'max:1000', 'starts_with:/'],
            'publish_at' => ['nullable', 'required_if:action,schedule', 'date', 'after:now'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        if ($data['target_type'] !== 'course') {
            $data['course_id'] = null;
        }
        if ($data['target_type'] !== 'grade') {
            $data['grade'] = null;
        }

        return $data;
    }
}
