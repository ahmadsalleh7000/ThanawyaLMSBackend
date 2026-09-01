<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['student_id', 'subscription_plan_id', 'academic_term_id', 'order_id', 'status', 'starts_at', 'ends_at', 'cancelled_at'])]
class StudentSubscription extends Model
{
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->where('starts_at', '<=', now())->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function term()
    {
        return $this->belongsTo(AcademicTerm::class, 'academic_term_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
