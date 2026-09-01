<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAdminPermission('finance.view');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->hasAdminPermission('finance.view') || $order->user_id === $user->id;
    }

    public function submitPaymentEvidence(User $user, Order $order): bool
    {
        return $order->user_id === $user->id
            && $order->payment_provider === 'manual_transfer'
            && in_array($order->status, ['pending', 'needs_information'], true);
    }

    public function refund(User $user, Order $order): bool
    {
        return $user->hasAdminPermission('refunds.issue');
    }
}
