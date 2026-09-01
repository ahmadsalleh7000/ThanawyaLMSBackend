<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AuditService;
use App\Services\OrderRefundService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminOrderController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Order::class);
        $query = $this->filtered($request)->with(['user:id,name,email', 'beneficiary:id,name,email', 'course:id,title,title_en', 'bundle:id,name,name_en', 'subscriptionPlan:id,name_ar,name_en']);

        return view('admin.orders.index', [
            'orders' => $query->latest()->paginate(25)->withQueryString(),
            'summary' => [
                'orders' => (clone $query)->count(),
                'revenue' => (float) (clone $query)->where('status', 'completed')->sum('amount'),
                'pending' => (clone $query)->where('status', 'pending')->count(),
                'refunded' => (float) (clone $query)->where('status', 'refunded')->sum('amount'),
            ],
        ]);
    }

    public function show(Order $order): View
    {
        Gate::authorize('view', $order);

        return view('admin.orders.show', ['order' => $order->load(['user', 'beneficiary', 'course', 'bundle.courses', 'subscriptionPlan', 'coupon', 'invoice'])]);
    }

    public function refund(Request $request, Order $order, OrderRefundService $refunds, AuditService $audit): RedirectResponse
    {
        Gate::authorize('refund', $order);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        $before = $order->attributesToArray();
        $refunds->refund($order, $data['reason'], $request->user());
        $audit->record($request, 'order.refunded', $order->refresh(), $before);

        return back()->with('success', 'تم تأكيد رد المبلغ وتحديث وصول الطالب.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->hasAdminPermission('finance.export'), 403);
        $fileName = 'orders-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($request): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Order ID', 'Student', 'Email', 'Product', 'Amount', 'Currency', 'Status', 'Provider', 'Created At']);
            $this->filtered($request)->with(['user:id,name,email', 'course:id,title_en', 'bundle:id,name_en', 'subscriptionPlan:id,name_en'])->latest()->chunk(500, function ($orders) use ($output): void {
                foreach ($orders as $order) {
                    fputcsv($output, [$order->public_id, $order->user->name, $order->user->email, $order->subscriptionPlan?->name_en ?? $order->bundle?->name_en ?? $order->course?->title_en ?? 'General order', $order->amount, $order->currency, $order->status, $order->payment_provider, $order->created_at->toISOString()]);
                }
            });
            fclose($output);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtered(Request $request): Builder
    {
        $query = Order::query();
        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('public_id', 'like', "%{$search}%")
                    ->orWhere('provider_payment_id', 'like', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('beneficiary', fn (Builder $student) => $student->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('course', fn (Builder $course) => $course->where('title', 'like', "%{$search}%")->orWhere('title_en', 'like', "%{$search}%"));
                $inner->orWhereHas('bundle', fn (Builder $bundle) => $bundle->where('name', 'like', "%{$search}%")->orWhere('name_en', 'like', "%{$search}%"));
            });
        }
        if ($status = $request->string('status')->toString()) {
            validator(['status' => $status], ['status' => [Rule::in(['pending', 'completed', 'failed', 'refunded'])]])->validate();
            $query->where('status', $status);
        }

        return $query;
    }
}
