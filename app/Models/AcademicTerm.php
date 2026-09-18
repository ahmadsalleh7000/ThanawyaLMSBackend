<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['academic_year_id', 'academic_grade_id', 'code', 'name_ar', 'name_en', 'display_order', 'starts_on', 'ends_on', 'is_active'])]
class AcademicTerm extends Model
{
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereHas('academicYear', fn (Builder $year) => $year->where('is_active', true));
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function grade()
    {
        return $this->belongsTo(AcademicGrade::class, 'academic_grade_id');
    }

    public function academicSubjects()
    {
        return $this->hasMany(AcademicSubject::class);
    }

    public function subscriptionPlans()
    {
        return $this->belongsToMany(SubscriptionPlan::class, 'subscription_plan_academic_term');
    }

    public function studentSubscriptions()
    {
        return $this->hasMany(StudentSubscription::class);
    }
}
