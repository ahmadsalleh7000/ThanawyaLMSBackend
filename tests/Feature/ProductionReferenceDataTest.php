<?php

namespace Tests\Feature;

use App\Models\AcademicGrade;
use App\Models\AcademicSubject;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\NotificationTemplate;
use App\Models\Subject;
use App\Models\SubscriptionPlan;
use App\Services\ProductionReferenceDataService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductionReferenceDataTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    public function test_missing_reference_graph_is_created_once_without_demo_content_or_placeholder_settings(): void
    {
        $this->clearReferenceData();

        $created = app(ProductionReferenceDataService::class)->ensure();

        $this->assertSame([
            'subscription_plans' => 2,
            'notification_templates' => 6,
            'academic_grades' => 3,
            'academic_years' => 1,
            'academic_terms' => 6,
            'subjects' => 1,
            'academic_subjects' => 6,
            'subscription_plan_academic_term' => 12,
        ], $created);
        $this->assertDatabaseHas('subscription_plans', ['code' => 'semester', 'price' => 750, 'currency' => 'EGP']);
        $this->assertDatabaseHas('subscription_plans', ['code' => 'full-year', 'price' => 1300, 'currency' => 'EGP']);
        $this->assertDatabaseHas('subjects', ['code' => 'programming']);
        foreach (['users', 'courses', 'lessons', 'orders', 'student_subscriptions', 'platform_settings'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }

        $this->assertSame(array_fill_keys(array_keys($created), 0), app(ProductionReferenceDataService::class)->ensure());
    }

    #[DataProvider('missingReferenceConfiguration')]
    public function test_incomplete_configuration_fails_before_any_reference_records_are_created(string $key, mixed $value): void
    {
        $this->clearReferenceData();
        config([$key => $value]);

        try {
            app(ProductionReferenceDataService::class)->ensure();
            $this->fail('Production setup must reject incomplete reference configuration.');
        } catch (DomainException $exception) {
            $this->assertSame('Production reference configuration is missing or empty. Rebuild it with php artisan config:cache and retry.', $exception->getMessage());
        }

        foreach ([
            'subscription_plans', 'notification_templates', 'academic_grades', 'academic_years',
            'academic_terms', 'subjects', 'academic_subjects', 'subscription_plan_academic_term',
        ] as $table) {
            $this->assertDatabaseEmpty($table);
        }
    }

    public static function missingReferenceConfiguration(): array
    {
        return [
            'missing root configuration' => ['production_setup', null],
            'empty subscription plans' => ['production_setup.subscription_plans', []],
            'empty notification templates' => ['production_setup.notification_templates', []],
            'empty academic grades' => ['production_setup.academic_grades', []],
            'empty academic terms' => ['production_setup.academic_terms', []],
            'empty subjects' => ['production_setup.subjects', []],
            'invalid mandatory list' => ['production_setup.subjects', 'programming'],
        ];
    }

    public function test_existing_prices_disabled_records_templates_and_restricted_scopes_are_preserved(): void
    {
        app(ProductionReferenceDataService::class)->ensure();

        $plan = SubscriptionPlan::where('code', 'semester')->firstOrFail();
        $plan->update(['price' => 999, 'name_en' => 'Custom plan', 'is_active' => false]);
        $template = NotificationTemplate::where('key', 'payment_approved')->firstOrFail();
        $template->update(['subject_en' => 'Custom approval', 'body_en' => 'Custom body', 'is_active' => false]);
        $grade = AcademicGrade::where('code', 'grade-1')->firstOrFail();
        $grade->update(['name_en' => 'Custom grade', 'is_active' => false]);
        $term = AcademicTerm::where('academic_grade_id', $grade->id)->orderBy('id')->firstOrFail();
        $term->update(['name_en' => 'Custom semester', 'is_active' => false]);
        $subject = Subject::where('code', 'programming')->firstOrFail();
        $subject->update(['name_en' => 'Custom programming', 'is_active' => false]);
        $offering = AcademicSubject::where('academic_term_id', $term->id)->firstOrFail();
        $offering->update(['display_order' => 9, 'is_active' => false]);
        $plan->academicTerms()->detach($term->id);
        $records = [$plan, $template, $grade, $term, $subject, $offering];
        $before = array_map(fn ($record) => $record->fresh()->getAttributes(), $records);

        $created = app(ProductionReferenceDataService::class)->ensure();

        $this->assertSame(0, array_sum($created));
        $this->assertSame($before, array_map(fn ($record) => $record->fresh()->getAttributes(), $records));
        $this->assertDatabaseMissing('subscription_plan_academic_term', ['subscription_plan_id' => $plan->id, 'academic_term_id' => $term->id]);
        $this->assertDatabaseCount('subscription_plan_academic_term', 11);
    }

    public function test_new_plan_gets_default_terms_without_restoring_an_existing_plans_removed_mapping(): void
    {
        app(ProductionReferenceDataService::class)->ensure();
        $existingPlan = SubscriptionPlan::where('code', 'full-year')->firstOrFail();
        $restrictedTerm = AcademicTerm::orderBy('id')->firstOrFail();
        $existingPlan->academicTerms()->detach($restrictedTerm->id);
        $missingPlan = SubscriptionPlan::where('code', 'semester')->firstOrFail();
        $missingPlan->academicTerms()->detach();
        $missingPlan->delete();

        $created = app(ProductionReferenceDataService::class)->ensure();

        $this->assertSame(1, $created['subscription_plans']);
        $this->assertSame(6, $created['subscription_plan_academic_term']);
        $this->assertSame(7, array_sum($created));
        $this->assertSame(6, SubscriptionPlan::where('code', 'semester')->firstOrFail()->academicTerms()->count());
        $this->assertDatabaseMissing('subscription_plan_academic_term', ['subscription_plan_id' => $existingPlan->id, 'academic_term_id' => $restrictedTerm->id]);
        $this->assertDatabaseCount('subscription_plan_academic_term', 11);
    }

    public function test_new_term_gets_default_plans_without_restoring_an_existing_terms_removed_mapping(): void
    {
        app(ProductionReferenceDataService::class)->ensure();
        $terms = AcademicTerm::orderBy('id')->limit(2)->get();
        $missingTerm = $terms[0];
        $restrictedTerm = $terms[1];
        $plan = SubscriptionPlan::where('code', 'full-year')->firstOrFail();
        $plan->academicTerms()->detach($restrictedTerm->id);
        AcademicSubject::where('academic_term_id', $missingTerm->id)->delete();
        $missingTerm->subscriptionPlans()->detach();
        $missingTerm->delete();

        $created = app(ProductionReferenceDataService::class)->ensure();

        $this->assertSame(1, $created['academic_terms']);
        $this->assertSame(1, $created['academic_subjects']);
        $this->assertSame(2, $created['subscription_plan_academic_term']);
        $this->assertSame(4, array_sum($created));
        $newTerm = AcademicTerm::where([
            'academic_year_id' => $missingTerm->academic_year_id,
            'academic_grade_id' => $missingTerm->academic_grade_id,
            'code' => $missingTerm->code,
        ])->firstOrFail();
        $this->assertSame(2, $newTerm->subscriptionPlans()->count());
        $this->assertDatabaseMissing('subscription_plan_academic_term', ['subscription_plan_id' => $plan->id, 'academic_term_id' => $restrictedTerm->id]);
        $this->assertDatabaseCount('subscription_plan_academic_term', 11);
    }

    public function test_existing_active_academic_year_is_reused_even_when_calendar_year_differs(): void
    {
        $this->clearReferenceData();
        $this->travelTo(Carbon::parse('2026-10-19 12:00:00'));
        $year = AcademicYear::create([
            'code' => 'custom-year', 'name_ar' => 'عام مخصص', 'name_en' => 'Custom year',
            'starts_on' => '2030-09-01', 'ends_on' => '2031-08-31', 'is_active' => true,
        ]);

        $created = app(ProductionReferenceDataService::class)->ensure();

        $this->assertSame(0, $created['academic_years']);
        $this->assertDatabaseCount('academic_years', 1);
        $this->assertSame(6, AcademicTerm::where('academic_year_id', $year->id)->count());
        $this->assertDatabaseHas('academic_years', ['id' => $year->id, 'code' => 'custom-year', 'name_en' => 'Custom year', 'is_active' => true]);
    }

    #[DataProvider('academicYearDates')]
    public function test_missing_academic_year_uses_september_to_august_in_application_timezone(string $instant, string $expectedCode, string $startsOn, string $endsOn): void
    {
        $this->clearReferenceData();
        config(['app.timezone' => 'Africa/Cairo']);
        $this->travelTo(Carbon::parse($instant, 'UTC'));

        app(ProductionReferenceDataService::class)->ensure();

        $this->assertDatabaseCount('academic_years', 1);
        $year = AcademicYear::where('code', $expectedCode)->firstOrFail();
        $this->assertSame($startsOn, $year->starts_on->toDateString());
        $this->assertSame($endsOn, $year->ends_on->toDateString());
        $this->assertTrue($year->is_active);
    }

    public static function academicYearDates(): array
    {
        return [
            'January belongs to previous September' => ['2026-01-15 12:00:00', '2025-2026', '2025-09-01', '2026-08-31'],
            'August belongs to previous September' => ['2026-08-15 12:00:00', '2025-2026', '2025-09-01', '2026-08-31'],
            'September starts a new academic year' => ['2026-09-01 12:00:00', '2026-2027', '2026-09-01', '2027-08-31'],
            'Cairo reaches September before UTC' => ['2026-08-31 22:30:00', '2026-2027', '2026-09-01', '2027-08-31'],
        ];
    }

    public function test_current_academic_year_is_not_reactivated_when_existing_record_is_disabled(): void
    {
        $this->clearReferenceData();
        $this->travelTo(Carbon::parse('2026-01-15 12:00:00'));
        $year = AcademicYear::create([
            'code' => '2025-2026', 'name_ar' => 'عام معطل', 'name_en' => 'Disabled year',
            'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31', 'is_active' => false,
        ]);

        $created = app(ProductionReferenceDataService::class)->ensure();

        $this->assertSame(0, $created['academic_years']);
        $this->assertDatabaseCount('academic_years', 1);
        $this->assertDatabaseHas('academic_years', ['id' => $year->id, 'name_en' => 'Disabled year', 'is_active' => false]);
        $this->assertFalse(AcademicYear::where('is_active', true)->exists());
    }

    private function clearReferenceData(): void
    {
        foreach ([
            'subscription_plan_academic_term',
            'academic_subjects',
            'academic_terms',
            'academic_years',
            'academic_grades',
            'subjects',
            'subscription_plans',
            'notification_templates',
        ] as $table) {
            DB::table($table)->delete();
        }
    }
}
