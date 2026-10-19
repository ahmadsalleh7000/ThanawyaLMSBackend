<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\ProductionReferenceDataService;
use App\Services\ReleaseReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ProductionSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'deployment.admin_name' => 'Production Owner',
            'deployment.admin_email' => 'owner@example.com',
            'deployment.admin_password' => 'UniqueProductionSecret42!',
        ]);
    }

    public function test_command_rejects_non_production_without_creating_an_admin(): void
    {
        $this->artisan('production:setup')->expectsOutputToContain('requires APP_ENV=production')->assertFailed();
        $this->assertDatabaseEmpty('users');
        $this->assertDatabaseEmpty('audit_logs');
    }

    public function test_command_populates_empty_reference_tables_and_reports_created_and_total_counts(): void
    {
        foreach ([
            'subscription_plan_academic_term', 'academic_subjects', 'academic_terms',
            'academic_years', 'academic_grades', 'subjects', 'notification_templates', 'subscription_plans',
        ] as $table) {
            DB::table($table)->delete();
        }
        $this->app->detectEnvironment(fn () => 'production');
        $totals = [
            'super_admin' => 1,
            'subscription_plans' => 2,
            'notification_templates' => 6,
            'academic_grades' => 3,
            'academic_years' => 1,
            'academic_terms' => 6,
            'subjects' => 1,
            'academic_subjects' => 6,
            'subscription_plan_academic_term' => 12,
        ];

        $this->artisan('production:setup')
            ->expectsTable(['Record type', 'Created', 'Total'], collect($totals)->map(fn (int $total, string $type) => [$type, $total, $total])->values()->all())
            ->assertSuccessful();

        $this->artisan('production:setup')
            ->expectsTable(['Record type', 'Created', 'Total'], collect($totals)->map(fn (int $total, string $type) => [$type, 0, $total])->values()->all())
            ->assertSuccessful();
    }

    public function test_missing_reference_configuration_fails_clearly_and_rolls_back_admin_creation(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['production_setup' => null]);

        $this->artisan('production:setup')->expectsOutputToContain('Rebuild it with php artisan config:cache')->assertFailed();

        $this->assertDatabaseEmpty('users');
        $this->assertDatabaseEmpty('audit_logs');
    }

    public function test_command_creates_a_login_ready_super_admin_and_can_be_repeated_without_bootstrap_secrets(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['deployment.admin_email' => ' OWNER@Example.com ']);

        $this->assertSame(0, Artisan::call('production:setup'));
        $this->assertStringNotContainsString('UniqueProductionSecret42!', Artisan::output());
        $admin = User::sole();
        $this->assertSame('owner@example.com', $admin->email);
        $this->assertSame('super_admin', $admin->adminRole());
        $this->assertTrue($admin->isEligibleForAccess());
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->assertFalse($admin->must_change_password);
        $this->assertTrue(Hash::check('UniqueProductionSecret42!', $admin->password));
        $audit = AuditLog::sole();
        $this->assertSame('production.admin.created', $audit->action);
        $this->assertArrayNotHasKey('password', $audit->after);
        $this->assertDatabaseEmpty('courses');
        $this->assertDatabaseEmpty('orders');
        $this->assertDatabaseEmpty('student_subscriptions');
        $this->assertDatabaseEmpty('platform_settings');

        $admin->update(['name' => 'Edited Owner', 'password' => 'ChangedProductionSecret42!']);
        $passwordHash = $admin->password;
        config(['deployment.admin_name' => null, 'deployment.admin_email' => null, 'deployment.admin_password' => null]);
        $this->assertSame(0, Artisan::call('production:setup'));
        $this->assertStringContainsString('Existing super administrator preserved.', Artisan::output());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame('Edited Owner', $admin->fresh()->name);
        $this->assertSame($passwordHash, $admin->fresh()->password);
        $this->assertTrue(app(ReleaseReadinessService::class)->checks()['admin_credentials']['ok']);

        $this->app->detectEnvironment(fn () => 'testing');
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'ChangedProductionSecret42!'])->assertRedirect();
        $this->assertAuthenticatedAs($admin);
    }

    #[DataProvider('invalidCredentials')]
    public function test_invalid_bootstrap_credentials_fail_before_any_reference_records_are_created(string $key, mixed $value): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(["deployment.{$key}" => $value]);
        NotificationTemplate::where('key', 'payment_approved')->delete();

        $this->artisan('production:setup')->assertFailed();

        $this->assertDatabaseEmpty('users');
        $this->assertDatabaseEmpty('audit_logs');
        $this->assertDatabaseMissing('notification_templates', ['key' => 'payment_approved']);
    }

    public static function invalidCredentials(): array
    {
        return [
            'missing name' => ['admin_name', null],
            'missing email' => ['admin_email', null],
            'invalid email' => ['admin_email', 'invalid'],
            'missing password' => ['admin_password', null],
            'short password' => ['admin_password', 'password123'],
            'demo password' => ['admin_password', 'TestingPassword123!'],
            'over bcrypt byte limit' => ['admin_password', str_repeat('ا', 40)],
        ];
    }

    #[DataProvider('collidingAccounts')]
    public function test_existing_accounts_are_never_promoted_or_reactivated(string $role, ?string $adminRole, bool $active): void
    {
        $existing = User::factory()->create(['email' => 'OWNER@example.com', 'role' => $role, 'admin_role' => $adminRole, 'is_active' => $active]);
        $original = $existing->refresh()->getAttributes();
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('production:setup')->assertFailed();

        $this->assertSame($original, $existing->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseEmpty('audit_logs');
        config(['deployment.admin_name' => null, 'deployment.admin_email' => null, 'deployment.admin_password' => null]);
        $this->assertFalse(app(ReleaseReadinessService::class)->checks()['admin_credentials']['ok']);
    }

    public static function collidingAccounts(): array
    {
        return [
            'student' => [User::ROLE_STUDENT, null, true],
            'limited staff' => [User::ROLE_ADMIN, 'content_editor', true],
            'disabled super admin' => [User::ROLE_ADMIN, 'super_admin', false],
        ];
    }

    #[DataProvider('legacyAdminRoles')]
    public function test_existing_legacy_super_admin_is_preserved_even_when_bootstrap_email_differs(?string $adminRole): void
    {
        User::factory()->create(['email' => 'legacy@example.com', 'role' => User::ROLE_ADMIN, 'admin_role' => $adminRole]);
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('production:setup')->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', ['email' => 'owner@example.com']);
    }

    public static function legacyAdminRoles(): array
    {
        return ['null role' => [null], 'empty role' => ['']];
    }

    public function test_reference_failure_rolls_back_admin_and_audit_creation_without_exposing_secrets(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->mock(ProductionReferenceDataService::class)->shouldReceive('ensure')->once()
            ->andThrow(new RuntimeException('Sensitive diagnostic: UniqueProductionSecret42!'));

        $this->assertSame(1, Artisan::call('production:setup'));

        $this->assertStringNotContainsString('UniqueProductionSecret42!', Artisan::output());
        $this->assertDatabaseEmpty('users');
        $this->assertDatabaseEmpty('audit_logs');
        $lock = Cache::lock('production-setup', 300);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_setup_refuses_to_run_concurrently(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $lock = Cache::lock('production-setup', 300);
        $this->assertTrue($lock->get());
        try {
            $this->artisan('production:setup')->expectsOutputToContain('Another production setup is running')->assertFailed();
            $this->assertDatabaseEmpty('users');
        } finally {
            $lock->release();
        }
    }

    public function test_setup_requires_current_migrations(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        Schema::drop('audit_logs');

        $this->artisan('production:setup')->expectsOutputToContain('migrate --force first')->assertFailed();

        $this->assertDatabaseEmpty('users');
    }
}
