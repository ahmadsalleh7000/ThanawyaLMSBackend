<?php

namespace Tests\Feature;

use App\Models\ApplicationIncident;
use App\Models\ManualPaymentSubmission;
use App\Models\QuestionBankQuestion;
use App\Models\StudentSupportCase;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\AdminDashboardTestingSeeder;
use Database\Seeders\AdminDemoSeeder;
use Database\Seeders\AdminNotificationDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EngagementDemoSeeder;
use Database\Seeders\LocalDevelopmentSeeder;
use Database\Seeders\ParentDemoSeeder;
use Database\Seeders\QuestionBankTestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AdminDashboardTestingSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_seed_data_is_created_once_and_exercises_role_specific_queues(): void
    {
        $this->seed(DatabaseSeeder::class);

        $counts = [
            'users' => User::count(),
            'payments' => ManualPaymentSubmission::count(),
            'support' => StudentSupportCase::count(),
            'incidents' => ApplicationIncident::count(),
            'questions' => QuestionBankQuestion::count(),
            'notifications' => UserNotification::count(),
        ];

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($counts['users'], User::count());
        $this->assertSame($counts['payments'], ManualPaymentSubmission::count());
        $this->assertSame($counts['support'], StudentSupportCase::count());
        $this->assertSame($counts['incidents'], ApplicationIncident::count());
        $this->assertSame($counts['questions'], QuestionBankQuestion::count());
        $this->assertSame($counts['notifications'], UserNotification::count());
        $this->assertDatabaseHas('platform_settings', ['key' => AdminDashboardTestingSeeder::MARKER, 'group' => 'testing']);
        $this->assertDatabaseHas('platform_settings', ['key' => AdminNotificationDemoSeeder::MARKER, 'group' => 'testing']);
        $this->assertDatabaseHas('platform_settings', ['key' => QuestionBankTestingSeeder::MARKER, 'group' => 'testing']);

        $finance = User::where('email', 'finance.dashboard@test.local')->firstOrFail();
        $this->actingAs($finance)->get(route('admin.dashboard'))
            ->assertOk()->assertSee('مراجعة التحويلات الجديدة')->assertSee('إيصالات متكررة');

        $support = User::where('email', 'support.dashboard@test.local')->firstOrFail();
        $this->actingAs($support)->get(route('admin.dashboard'))
            ->assertOk()->assertSee('طلاب مرتفعو المخاطر')->assertSee('متابعات متأخرة');

        $content = User::where('email', 'content.dashboard@test.local')->firstOrFail();
        $this->actingAs($content)->get(route('admin.question-bank.index', ['status' => 'inactive']))
            ->assertOk()->assertSee('يجب الوثوق بمدخلات المستخدم')->assertDontSee('ما الدالة التي تطبع نصاً');
    }

    public function test_seeders_refuse_to_run_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        try {
            foreach ([
                DatabaseSeeder::class,
                LocalDevelopmentSeeder::class,
                AdminDemoSeeder::class,
                ParentDemoSeeder::class,
                EngagementDemoSeeder::class,
                AdminDashboardTestingSeeder::class,
                AdminNotificationDemoSeeder::class,
                QuestionBankTestingSeeder::class,
            ] as $seeder) {
                try {
                    app($seeder)->run();
                    $this->fail("{$seeder} was allowed to run in production.");
                } catch (RuntimeException $exception) {
                    $this->assertStringContainsString('must never be seeded in the production environment', $exception->getMessage());
                }
            }
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }

        $this->assertDatabaseMissing('platform_settings', ['key' => AdminDashboardTestingSeeder::MARKER]);
    }

    public function test_dashboard_seeder_runs_in_a_non_production_staging_environment(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');
        try {
            app(AdminDashboardTestingSeeder::class)->run();
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }

        $this->assertDatabaseHas('platform_settings', ['key' => AdminDashboardTestingSeeder::MARKER]);
    }
}
