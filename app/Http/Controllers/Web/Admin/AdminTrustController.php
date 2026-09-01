<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CourseReview;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminTrustController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $reviews = CourseReview::query()->with(['user:id,name,email', 'course:id,title,title_en'])
            ->when(in_array($status, ['pending', 'published', 'rejected'], true), fn ($query) => $query->where('status', $status))
            ->latest()->paginate(12, ['*'], 'reviews')->withQueryString();
        $certificates = Certificate::query()->with(['user:id,name,email', 'course:id,title,title_en'])
            ->when($request->boolean('revoked'), fn ($query) => $query->whereNotNull('revoked_at'), fn ($query) => $query->whereNull('revoked_at'))
            ->latest('issued_at')->paginate(12, ['*'], 'certificates')->withQueryString();

        return view('admin.trust.index', [
            'reviews' => $reviews,
            'certificates' => $certificates,
            'counts' => [
                'pending' => CourseReview::where('status', 'pending')->count(),
                'published' => CourseReview::where('status', 'published')->count(),
                'activeCertificates' => Certificate::whereNull('revoked_at')->count(),
                'revokedCertificates' => Certificate::whereNotNull('revoked_at')->count(),
            ],
        ]);
    }

    public function moderate(Request $request, CourseReview $review, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['published', 'rejected'])],
            'moderation_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $before = $review->attributesToArray();
        $review->update($data + ['moderated_by' => $request->user()->id, 'moderated_at' => now()]);
        $audit->record($request, 'course_review.'.$data['status'], $review, $before);
        $this->refreshRating($review);

        return back()->with('success', $data['status'] === 'published' ? 'تم نشر التقييم.' : 'تم رفض التقييم.');
    }

    public function revoke(Request $request, Certificate $certificate, AuditService $audit): RedirectResponse
    {
        abort_if($certificate->revoked_at, 409, 'Certificate is already revoked.');
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $before = $certificate->attributesToArray();
        $certificate->update(['revoked_at' => now(), 'revocation_reason' => $data['reason'], 'revoked_by' => $request->user()->id]);
        $audit->record($request, 'certificate.revoked', $certificate, $before);

        return back()->with('success', 'تم إلغاء الشهادة ومنع التحقق العام منها.');
    }

    private function refreshRating(CourseReview $review): void
    {
        $rating = $review->course->reviews()->published()->avg('rating') ?? 0;
        $review->course->update(['rating' => round((float) $rating, 1)]);
    }
}
