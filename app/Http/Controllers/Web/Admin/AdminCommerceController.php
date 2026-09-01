<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\CourseBundle;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Services\AuditService;
use App\Services\PlatformSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminCommerceController extends Controller
{
    public function index(PlatformSettingsService $settings): View
    {
        return view('admin.commerce.index', [
            'summary' => $this->commerceSummary(),
            'paymentSettings' => $settings->all(),
            'subscriptionPlans' => SubscriptionPlan::orderBy('display_order')->get(),
            'couponsCount' => Coupon::count(),
            'bundlesCount' => CourseBundle::count(),
        ]);
    }

    public function subscriptionPlans(): View
    {
        return view('admin.commerce.subscription-plans', [
            'subscriptionPlans' => SubscriptionPlan::orderBy('display_order')->get(),
        ]);
    }

    public function promoCodes(): View
    {
        $coupons = Coupon::with([
            'courses:id,title',
            'subscriptionPlans:id,name_ar',
            'redemptions' => fn ($query) => $query->latest(),
            'redemptions.user:id,name,email',
            'redemptions.order:id,public_id,amount,discount_amount,status,created_at',
        ])
            ->withCount([
                'redemptions as redeemed_count' => fn ($query) => $query->where('status', 'redeemed'),
                'redemptions as reserved_count' => fn ($query) => $query->where('status', 'reserved'),
            ])
            ->latest()->get();

        return view('admin.commerce.promo-codes', [
            'courses' => Course::whereNull('archived_at')->orderBy('title')->get(['id', 'title']),
            'coupons' => $coupons,
            'promoSummary' => [
                'total' => $coupons->count(),
                'active' => $coupons->where('is_active', true)->count(),
                'scheduled' => $coupons->filter(fn (Coupon $coupon) => $coupon->starts_at?->isFuture())->count(),
                'expired' => $coupons->filter(fn (Coupon $coupon) => $coupon->expires_at?->isPast())->count(),
            ],
            'subscriptionPlans' => SubscriptionPlan::orderBy('display_order')->get(),
        ]);
    }

    public function bundles(): View
    {
        return view('admin.commerce.bundles', [
            'courses' => Course::whereNull('archived_at')->orderBy('title')->get(['id', 'title']),
            'bundles' => CourseBundle::with('courses:id,title')->latest()->get(),
        ]);
    }

    public function paymentSettings(PlatformSettingsService $settings): View
    {
        return view('admin.commerce.payment-settings', [
            'paymentSettings' => $settings->all(),
        ]);
    }

    public function storeSubscriptionPlan(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80', 'unique:subscription_plans,code'],
            'name_ar' => ['required', 'string', 'max:150'], 'name_en' => ['required', 'string', 'max:150'],
            'billing_period' => ['required', Rule::in(['semester', 'year'])],
            'price' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);
        $plan = SubscriptionPlan::create($data + ['currency' => 'EGP', 'is_active' => true]);
        $audit->record($request, 'subscription_plan.created', $plan);

        return back()->with('success', 'تم إنشاء خطة الاشتراك.');
    }

    public function updateSubscriptionPlan(Request $request, SubscriptionPlan $plan, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:150'], 'name_en' => ['required', 'string', 'max:150'],
            'billing_period' => ['required', Rule::in(['semester', 'year'])],
            'price' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
            'display_order' => ['required', 'integer', 'min:0', 'max:1000'], 'is_active' => ['required', 'boolean'],
        ]);
        $before = $plan->attributesToArray();
        $plan->update($data);
        $audit->record($request, 'subscription_plan.updated', $plan, $before);

        return back()->with('success', 'تم تحديث خطة الاشتراك.');
    }

    public function updatePaymentSettings(Request $request, PlatformSettingsService $settings, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'instapay_destination' => ['nullable', 'string', 'max:255'],
            'vodafone_cash_number' => ['nullable', 'string', 'max:50'],
            'orange_cash_number' => ['nullable', 'string', 'max:50'],
            'etisalat_cash_number' => ['nullable', 'string', 'max:50'],
            'manual_payment_expires_minutes' => ['required', 'integer', 'min:30', 'max:10080'],
        ]);
        $settings->update($data, $request->user());
        $audit->record($request, 'payment_destinations.updated', $request->user());

        return back()->with('success', 'تم تحديث حسابات استقبال التحويلات.');
    }

    public function storeCoupon(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $this->validateCouponPayload($request);
        abort_if($data['type'] === 'percent' && (float) $data['value'] > 100, 422, 'Percentage cannot exceed 100.');
        $courseIds = $data['course_ids'] ?? [];
        $subscriptionPlanIds = $data['subscription_plan_ids'] ?? [];
        unset($data['course_ids'], $data['subscription_plan_ids']);
        $data['code'] = mb_strtoupper(trim($data['code']));
        $data['minimum_amount'] ??= 0;
        $data['is_active'] = true;
        $coupon = Coupon::create($data);
        $coupon->courses()->sync($courseIds);
        $coupon->subscriptionPlans()->sync($subscriptionPlanIds);
        $audit->record($request, 'coupon.created', $coupon);

        return back()->with('success', 'تم إنشاء كود الخصم بنجاح.');
    }

    public function updateCoupon(Request $request, Coupon $coupon, AuditService $audit): RedirectResponse
    {
        $data = $this->validateCouponPayload($request, $coupon);
        abort_if($data['type'] === 'percent' && (float) $data['value'] > 100, 422, 'Percentage cannot exceed 100.');
        $courseIds = $data['course_ids'] ?? [];
        $subscriptionPlanIds = $data['subscription_plan_ids'] ?? [];
        unset($data['course_ids'], $data['subscription_plan_ids']);
        $data['code'] = mb_strtoupper(trim($data['code']));
        $data['minimum_amount'] ??= 0;
        $data['is_active'] = $request->boolean('is_active');
        $before = $coupon->attributesToArray();
        $coupon->update($data);
        $coupon->courses()->sync($courseIds);
        $coupon->subscriptionPlans()->sync($subscriptionPlanIds);
        $audit->record($request, 'coupon.updated', $coupon, $before);

        return back()->with('success', 'تم تحديث كود الخصم.');
    }

    public function toggleCoupon(Request $request, Coupon $coupon, AuditService $audit): RedirectResponse
    {
        $before = $coupon->attributesToArray();
        $coupon->update(['is_active' => ! $coupon->is_active]);
        $audit->record($request, 'coupon.toggled', $coupon, $before);

        return back()->with('success', 'تم تحديث حالة كود الخصم.');
    }

    public function destroyCoupon(Request $request, Coupon $coupon, AuditService $audit): RedirectResponse
    {
        if ($coupon->redemptions()->exists()) {
            return back()->withErrors(['coupon' => 'لا يمكن حذف كود مستخدم. أوقفه للحفاظ على سجل الاستخدام.']);
        }

        $before = $coupon->attributesToArray();
        $coupon->courses()->detach();
        $coupon->subscriptionPlans()->detach();
        $audit->record($request, 'coupon.deleted', $coupon, $before);
        $coupon->delete();

        return back()->with('success', 'تم حذف كود الخصم.');
    }

    public function storeBundle(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'gt:0'],
            'course_ids' => ['required', 'array', 'min:2'],
            'course_ids.*' => ['string', 'distinct', 'exists:courses,id'],
        ]);
        $courseIds = $data['course_ids'];
        unset($data['course_ids']);
        $bundle = CourseBundle::create($data + ['is_active' => true]);
        $orderedCourseIds = array_values($courseIds);
        $bundle->courses()->sync(collect($orderedCourseIds)->mapWithKeys(fn ($courseId, $index) => [$courseId => ['position' => $index + 1, 'prerequisite_course_id' => $index ? $orderedCourseIds[$index - 1] : null]])->all());
        $audit->record($request, 'bundle.created', $bundle);

        return back()->with('success', 'تم إنشاء الباقة التعليمية.');
    }

    public function toggleBundle(Request $request, CourseBundle $bundle, AuditService $audit): RedirectResponse
    {
        $before = $bundle->attributesToArray();
        $bundle->update(['is_active' => ! $bundle->is_active]);
        $audit->record($request, 'bundle.toggled', $bundle, $before);

        return back()->with('success', 'تم تحديث حالة الباقة.');
    }

    public function invoice(Invoice $invoice): View
    {
        return view('admin.commerce.invoice', ['invoice' => $invoice->load('order')]);
    }

    private function commerceSummary(): array
    {
        return [
            'gross' => (float) Order::where('status', 'completed')->sum('original_amount'),
            'discounts' => (float) Order::where('status', 'completed')->sum('discount_amount'),
            'collected' => (float) Order::where('status', 'completed')->sum('amount'),
            'refunded' => (float) Order::where('status', 'refunded')->sum('amount'),
            'invoices' => Invoice::count(),
        ];
    }

    private function validateCouponPayload(Request $request, ?Coupon $coupon = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:100', Rule::unique('coupons', 'code')->ignore($coupon)],
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'gt:0'],
            'minimum_amount' => ['nullable', 'numeric', 'min:0'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'max_per_user' => ['required', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
            'is_active' => ['nullable', 'boolean'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['string', 'exists:courses,id'],
            'subscription_plan_ids' => ['nullable', 'array'],
            'subscription_plan_ids.*' => ['integer', 'exists:subscription_plans,id'],
        ]);
    }
}
