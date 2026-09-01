<?php

namespace App\Services;

use App\Models\ManualPaymentSubmission;
use App\Models\Order;
use App\Models\PaymentReconciliationPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentReconciliationService
{
    public function create(array $data, User $actor): PaymentReconciliationPeriod
    {
        $overlaps = PaymentReconciliationPeriod::where('starts_on', '<=', $data['ends_on'])->where('ends_on', '>=', $data['starts_on'])->exists();
        if ($overlaps) {
            throw ValidationException::withMessages(['starts_on' => 'This date range overlaps an existing reconciliation period.']);
        }
        $period = PaymentReconciliationPeriod::create($data + ['status' => 'open', 'created_by' => $actor->id]);
        foreach (array_keys(config('payments.manual_transfer.channels')) as $channel) {
            $period->lines()->create(['channel' => $channel]);
        }
        $this->refresh($period);

        return $period->refresh();
    }

    public function refresh(PaymentReconciliationPeriod $period): void
    {
        if ($period->status !== 'open') {
            return;
        }
        foreach ($period->lines as $line) {
            $approved = ManualPaymentSubmission::where('channel', $line->channel)->where('status', 'approved')->whereBetween('transferred_at', [$period->starts_on->startOfDay(), $period->ends_on->endOfDay()]);
            $refunds = Order::where('status', 'refunded')->whereBetween('refunded_at', [$period->starts_on->startOfDay(), $period->ends_on->endOfDay()])
                ->whereHas('manualPaymentSubmissions', fn ($submission) => $submission->where('channel', $line->channel)->where('status', 'approved'));
            $line->update(['expected_inflow' => (clone $approved)->sum('submitted_amount'), 'approved_count' => (clone $approved)->count(), 'expected_refunds' => (clone $refunds)->sum('amount'), 'refund_count' => (clone $refunds)->count()]);
        }
    }

    public function close(PaymentReconciliationPeriod $period, User $actor, ?string $note): PaymentReconciliationPeriod
    {
        return DB::transaction(function () use ($period, $actor, $note) {
            $locked = PaymentReconciliationPeriod::lockForUpdate()->findOrFail($period->id);
            if ($locked->status !== 'open') {
                throw ValidationException::withMessages(['period' => 'This period is already closed.']);
            }
            $this->refresh($locked);
            if ($locked->lines()->whereNull('actual_inflow')->orWhereNull('actual_refunds')->exists()) {
                throw ValidationException::withMessages(['period' => 'Enter statement totals for every channel before closing.']);
            }
            $locked->update(['status' => 'closed', 'closed_by' => $actor->id, 'closed_at' => now(), 'close_note' => $note]);
            $locked->alerts()->where('type', 'overdue')->whereNull('resolved_at')->update(['resolved_at' => now()]);

            return $locked->refresh();
        });
    }
}
