<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Order;
use App\Models\StudentSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentSubscriptionService
{
    public function activateFor(Order $order): void
    {
        if ($order->product_type !== 'subscription' || ! $order->subscriptionPlan) {
            return;
        }
        $student = $order->beneficiary ?? $order->user;
        $profile = $student->onboardingProfile;
        $grade = $profile?->grade;
        $semester = $profile?->semester;

        if (! $grade || ! $semester) {
            throw ValidationException::withMessages([
                'grade' => ['A grade and semester are required before activating a subscription.'],
            ]);
        }

        $terms = AcademicTerm::active()->whereHas('grade', fn ($query) => $query->where('code', $grade))
            ->where('code', $semester)
            ->whereHas('subscriptionPlans', fn ($query) => $query->whereKey($order->subscription_plan_id))->get();

        if ($terms->isEmpty()) {
            throw ValidationException::withMessages([
                'subscription_plan_id' => ['The selected subscription plan is not available for this grade and semester.'],
            ]);
        }

        DB::transaction(function () use ($terms, $order, $student): void {
            $student->newQuery()->whereKey($student->id)->lockForUpdate()->firstOrFail();

            foreach ($terms as $term) {
                $alreadyActive = StudentSubscription::query()
                    ->where('student_id', $student->id)
                    ->where('subscription_plan_id', $order->subscription_plan_id)
                    ->where('academic_term_id', $term->id)
                    ->where('status', 'active')
                    ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                    ->exists();

                if ($alreadyActive) {
                    continue;
                }

                StudentSubscription::updateOrCreate(
                    ['student_id' => $student->id, 'academic_term_id' => $term->id, 'order_id' => $order->id],
                    ['subscription_plan_id' => $order->subscription_plan_id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => $term->ends_on?->endOfDay()],
                );
            }
        });
    }
}
