<?php

namespace Tests\Feature;

use App\Jobs\CreateDatabaseBackup;
use App\Models\ManualPaymentSubmission;
use App\Models\PlatformSetting;
use App\Models\SystemBackup;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_backup_is_encrypted_and_checksum_verified(): void
    {
        Storage::fake('local');
        $database = tempnam(sys_get_temp_dir(), 'lms-db-');
        file_put_contents($database, 'private database contents');
        $backup = SystemBackup::create(['database_driver' => 'sqlite', 'status' => 'queued']);
        $original = config('database.connections.sqlite.database');
        try {
            config(['database.connections.sqlite.database' => $database]);
            app(DatabaseBackupService::class)->create($backup);
            $backup->refresh();
            Storage::disk('local')->assertExists($backup->path);
            $this->assertNotSame('private database contents', Storage::disk('local')->get($backup->path));
            $this->assertTrue(app(DatabaseBackupService::class)->verify($backup));
            $this->assertNotNull($backup->fresh()->verified_at);
        } finally {
            config(['database.connections.sqlite.database' => $original]);
            unlink($database);
        }
    }

    public function test_system_admin_requests_backup_and_manages_retention_policy(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => null]);
        $this->actingAs($admin)->get(route('admin.system.operations.index'))->assertOk()->assertSee('التشغيل والتعافي');
        $this->actingAs($admin)->post(route('admin.system.operations.backups.store'))->assertRedirect();
        $backup = SystemBackup::firstOrFail();
        Queue::assertPushed(CreateDatabaseBackup::class, fn ($job) => $job->backupId === $backup->id);
        $this->actingAs($admin)->put(route('admin.system.operations.policies.update'), ['payment_receipt_retention_days' => 180, 'database_backup_retention_days' => 14, 'queue_stale_minutes' => 10])->assertRedirect();
        $this->assertDatabaseHas('platform_settings', ['key' => 'payment_receipt_retention_days']);

        $editor = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor']);
        $this->actingAs($editor)->get(route('admin.system.operations.index'))->assertForbidden();
    }

    public function test_retention_deletes_private_files_but_preserves_audit_metadata(): void
    {
        Storage::fake('local');
        PlatformSetting::create(['key' => 'payment_receipt_retention_days', 'value' => 30, 'group' => 'general']);
        PlatformSetting::create(['key' => 'database_backup_retention_days', 'value' => 7, 'group' => 'general']);
        $student = User::factory()->create(['role' => 'student']);
        $order = $student->orders()->create(['public_id' => fake()->uuid(), 'amount' => 100, 'original_amount' => 100, 'currency' => 'EGP', 'status' => 'rejected', 'payment_provider' => 'manual_transfer', 'payment_method' => 'instapay', 'session_token_hash' => hash('sha256', fake()->uuid())]);
        Storage::disk('local')->put('payment-receipts/old.jpg', 'receipt');
        $submission = ManualPaymentSubmission::create(['order_id' => $order->id, 'user_id' => $student->id, 'channel' => 'instapay', 'transaction_id' => 'RETENTION-1', 'sender_identifier' => 'sender', 'transferred_at' => now()->subDays(40), 'submitted_amount' => 100, 'receipt_path' => 'payment-receipts/old.jpg', 'receipt_original_name' => 'old.jpg', 'receipt_mime' => 'image/jpeg', 'receipt_size' => 7, 'receipt_sha256' => hash('sha256', 'receipt'), 'status' => 'rejected']);
        $submission->forceFill(['created_at' => now()->subDays(40)])->saveQuietly();
        Storage::disk('local')->put('backups/old.enc', 'encrypted');
        $backup = SystemBackup::create(['path' => 'backups/old.enc', 'database_driver' => 'mysql', 'status' => 'completed', 'content_sha256' => str_repeat('a', 64)]);
        $backup->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

        $this->artisan('system:enforce-retention')->assertSuccessful();
        Storage::disk('local')->assertMissing('payment-receipts/old.jpg');
        Storage::disk('local')->assertMissing('backups/old.enc');
        $this->assertNotNull($submission->fresh()->receipt_deleted_at);
        $this->assertSame('RETENTION-1', $submission->fresh()->transaction_id);
        $this->assertDatabaseHas('system_backups', ['id' => $backup->id, 'status' => 'pruned', 'path' => null]);
    }
}
