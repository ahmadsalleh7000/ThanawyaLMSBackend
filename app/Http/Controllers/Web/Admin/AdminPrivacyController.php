<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminPrivacyController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'cancelled', 'completed', 'failed'])]])['status'] ?? null;
        $query = AccountDeletionRequest::with(['user:id,name,email,role,is_active,anonymized_at', 'reviewer:id,name'])->latest();
        if ($status) {
            $query->where('status', $status);
        }

        return view('admin.privacy.index', [
            'requests' => $query->paginate(30)->withQueryString(),
            'selectedStatus' => $status,
            'counts' => AccountDeletionRequest::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'retentionYears' => (int) config('privacy.financial_record_retention_years'),
        ]);
    }

    public function review(Request $request, AccountDeletionRequest $deletion, AuditService $audit): RedirectResponse
    {
        abort_unless($deletion->status === 'pending', 409, 'This request has already been reviewed.');
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'review_note' => ['required', 'string', 'min:5', 'max:2000']]);
        $before = $deletion->attributesToArray();
        $deletion->update(['status' => $data['decision'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['review_note'], 'processing_error' => null]);
        $deletion->user->userNotifications()->create([
            'type' => 'privacy',
            'title' => $data['decision'] === 'approved' ? 'Account deletion approved' : 'Account deletion request rejected',
            'title_ar' => $data['decision'] === 'approved' ? 'تمت الموافقة على حذف الحساب' : 'تم رفض طلب حذف الحساب',
            'message' => $data['review_note'],
            'message_ar' => $data['review_note'],
            'action_url' => '/profile',
            'data' => ['status' => $data['decision'], 'scheduledFor' => $deletion->scheduled_for->toISOString()],
        ]);
        $audit->record($request, 'privacy.deletion_'.$data['decision'], $deletion, $before);

        return back()->with('success', $data['decision'] === 'approved' ? 'تمت الموافقة وسينفذ الحذف في موعده.' : 'تم رفض الطلب مع تسجيل السبب.');
    }
}
