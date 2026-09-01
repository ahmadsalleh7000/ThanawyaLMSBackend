<?php

namespace Tests\Feature;

use App\Models\ApplicationIncident;
use App\Models\Course;
use App\Models\LoginEvent;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\PlatformSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ObservabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_and_admin_requests_are_instrumented_without_storing_urls(): void
    {
        $this->getJson('/api/courses')->assertOk();
        $this->assertDatabaseHas('request_metrics', ['route_name' => null, 'method' => 'GET', 'status_code' => 200]);
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => null]);
        $this->actingAs($admin)->get(route('admin.system.observability.index'))->assertOk()->assertSee('المراقبة والجاهزية');
        $this->assertDatabaseHas('request_metrics', ['route_name' => 'admin.system.observability.index', 'status_code' => 200]);
        $editor = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor']);
        $this->actingAs($editor)->get(route('admin.system.observability.index'))->assertForbidden();
    }

    public function test_health_monitor_deduplicates_security_incident_and_admin_resolves_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => null]);
        app(PlatformSettingsService::class)->update(['observability_failed_login_threshold' => 3], $admin);
        foreach (range(1, 3) as $index) {
            LoginEvent::create(['email' => "attacker{$index}@example.com", 'guard' => 'web', 'successful' => false, 'failure_reason' => 'invalid_credentials', 'occurred_at' => now()]);
        }
        $this->artisan('ops:monitor-health')->assertSuccessful();
        $this->artisan('ops:monitor-health')->assertSuccessful();
        $incident = ApplicationIncident::where('type', 'security')->firstOrFail();
        $this->assertSame(2, $incident->occurrences);
        $this->actingAs($admin)->post(route('admin.system.observability.incidents.resolve', $incident), ['resolution_note' => 'Blocked the abusive source and reviewed accounts.'])->assertRedirect();
        $this->assertNotNull($incident->fresh()->resolved_at);
    }

    public function test_public_readiness_endpoint_returns_only_summary_status(): void
    {
        Storage::fake('local');
        Cache::put('ops.scheduler_heartbeat', now()->toISOString(), now()->addHour());
        config(['app.debug' => false, 'app.key' => 'base64:'.base64_encode(random_bytes(32)), 'queue.default' => 'database']);

        $this->getJson('/health/ready')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertExactJson(['status' => 'ready']);
    }

    public function test_responses_include_baseline_browser_security_headers(): void
    {
        $this->getJson('/api/courses')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
    }

    public function test_signed_media_streams_can_be_framed_by_frontend_origin(): void
    {
        Storage::fake('local');
        config(['cors.allowed_origins' => ['http://localhost:3000']]);

        $course = Course::factory()->create(['is_published' => true]);
        $lesson = $course->lessons()->create([
            'slug' => 'frame-pdf',
            'title' => 'PDF',
            'title_en' => 'PDF',
            'duration_minutes' => 10,
            'position' => 1,
            'status' => 'published',
            'is_preview' => true,
        ]);
        Storage::disk('local')->put('media-library/frame.pdf', '%PDF-1.4');
        $media = MediaAsset::create([
            'lesson_id' => $lesson->id,
            'kind' => 'lesson_pdf',
            'disk' => 'local',
            'path' => 'media-library/frame.pdf',
            'original_name' => 'frame.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 8,
        ]);

        $this->get(URL::temporarySignedRoute('media.stream', now()->addMinute(), ['media' => $media->id]))
            ->assertOk()
            ->assertHeaderMissing('X-Frame-Options')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self' http://localhost:3000");
    }

    public function test_html_responses_receive_csp_and_admin_responses_are_not_cacheable(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'admin_role' => null]);

        $response = $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertHeader('Content-Security-Policy')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Pragma', 'no-cache');

        $this->assertStringContainsString(
            "form-action 'self' https://web.whatsapp.com https://wa.me",
            (string) $response->headers->get('Content-Security-Policy'),
        );
    }
}
