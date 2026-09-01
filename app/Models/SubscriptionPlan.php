<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name_ar', 'name_en', 'billing_period', 'price', 'currency', 'is_active', 'display_order'])]
class SubscriptionPlan extends Model
{
    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function academicTerms()
    {
        return $this->belongsToMany(AcademicTerm::class, 'subscription_plan_academic_term');
    }

    public function studentSubscriptions()
    {
        return $this->hasMany(StudentSubscription::class);
    }
}
