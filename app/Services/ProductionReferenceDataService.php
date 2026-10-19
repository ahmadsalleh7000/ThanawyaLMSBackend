<?php

namespace App\Services;

use App\Models\AcademicGrade;
use App\Models\AcademicSubject;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\NotificationTemplate;
use App\Models\Subject;
use App\Models\SubscriptionPlan;

class ProductionReferenceDataService
{
    /**
     * Insert missing defaults without changing existing records or access scopes.
     * The caller is responsible for wrapping setup in a transaction.
     *
     * @return array<string, int>
     */
    public function ensure(): array
    {
        $created = array_fill_keys([
            'subscription_plans',
            'notification_templates',
            'academic_grades',
            'academic_years',
            'academic_terms',
            'subjects',
            'academic_subjects',
            'subscription_plan_academic_term',
        ], 0);

        $plans = [];
        foreach (config('production_setup.subscription_plans', []) as $defaults) {
            $plan = SubscriptionPlan::firstOrCreate(['code' => $defaults['code']], $defaults);
            $created['subscription_plans'] += (int) $plan->wasRecentlyCreated;
            $plans[] = $plan;
        }

        foreach (config('production_setup.notification_templates', []) as $defaults) {
            $template = NotificationTemplate::firstOrCreate(['key' => $defaults['key']], $defaults);
            $created['notification_templates'] += (int) $template->wasRecentlyCreated;
        }

        $year = $this->academicYear();
        $created['academic_years'] += (int) $year->wasRecentlyCreated;

        $subjects = [];
        foreach (config('production_setup.subjects', []) as $defaults) {
            $subject = Subject::firstOrCreate(['code' => $defaults['code']], $defaults);
            $created['subjects'] += (int) $subject->wasRecentlyCreated;
            $subjects[] = $subject;
        }

        foreach (config('production_setup.academic_grades', []) as $defaults) {
            $grade = AcademicGrade::firstOrCreate(['code' => $defaults['code']], $defaults);
            $created['academic_grades'] += (int) $grade->wasRecentlyCreated;

            foreach (config('production_setup.academic_terms', []) as $termDefaults) {
                $term = AcademicTerm::firstOrCreate([
                    'academic_year_id' => $year->id,
                    'academic_grade_id' => $grade->id,
                    'code' => $termDefaults['code'],
                ], $termDefaults);
                $created['academic_terms'] += (int) $term->wasRecentlyCreated;

                foreach ($subjects as $subject) {
                    $offering = AcademicSubject::firstOrCreate([
                        'academic_term_id' => $term->id,
                        'subject_id' => $subject->id,
                    ], ['display_order' => 0, 'is_active' => true]);
                    $created['academic_subjects'] += (int) $offering->wasRecentlyCreated;
                }

                foreach ($plans as $plan) {
                    // A removed mapping between existing records may be intentional.
                    if (! $plan->wasRecentlyCreated && ! $term->wasRecentlyCreated) {
                        continue;
                    }

                    $changes = $plan->academicTerms()->syncWithoutDetaching([$term->id]);
                    $created['subscription_plan_academic_term'] += count($changes['attached']);
                }
            }
        }

        // Platform settings already use code defaults; leave placeholder contact
        // and payment destinations implicit until an administrator configures them.
        return $created;
    }

    private function academicYear(): AcademicYear
    {
        $activeYear = AcademicYear::where('is_active', true)->orderBy('id')->first();
        if ($activeYear) {
            return $activeYear;
        }

        $now = now(config('app.timezone'));
        $startsIn = $now->month >= 9 ? $now->year : $now->year - 1;
        $endsIn = $startsIn + 1;

        return AcademicYear::firstOrCreate(['code' => $startsIn.'-'.$endsIn], [
            'name_ar' => 'العام الدراسي '.$startsIn.'/'.$endsIn,
            'name_en' => 'Academic year '.$startsIn.'/'.$endsIn,
            'starts_on' => $startsIn.'-09-01',
            'ends_on' => $endsIn.'-08-31',
            'is_active' => true,
        ]);
    }
}
