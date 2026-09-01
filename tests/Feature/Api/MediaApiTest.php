<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_uploads_private_video_and_only_enrolled_student_gets_signed_access(): void
    {
        Storage::fake('local');
        $course = $this->course();
        $lesson = $course->lessons()->create(['slug' => 'private-video', 'title' => 'فيديو', 'title_en' => 'Video', 'duration_minutes' => 10, 'position' => 1]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $asset = $this->postJson('/api/admin/courses/'.$course->id.'/lessons/'.$lesson->id.'/media', ['kind' => 'video', 'file' => UploadedFile::fake()->create('lesson.mp4', 100, 'video/mp4')])
            ->assertCreated()->assertJsonPath('data.kind', 'video')->json('data');
        $this->assertDatabaseHas('media_assets', ['id' => $asset['id'], 'lesson_id' => $lesson->id]);

        $student = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($student);
        $this->getJson('/api/media/'.$asset['id'].'/access')->assertForbidden();
        $student->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now()]);
        $url = $this->getJson('/api/media/'.$asset['id'].'/access')->assertOk()->assertJsonStructure(['data' => ['url', 'expiresAt']])->json('data.url');
        $this->getJson(parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY))->assertOk()->assertHeader('content-type', 'video/mp4');
    }

    public function test_preview_media_uses_expiring_signed_url_without_enrollment(): void
    {
        Storage::fake('local');
        $course = $this->course('preview-media-course');
        $lesson = $course->lessons()->create(['slug' => 'preview', 'title' => 'تجريبي', 'title_en' => 'Preview', 'duration_minutes' => 5, 'position' => 1, 'is_preview' => true]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $asset = $this->postJson('/api/admin/courses/'.$course->id.'/lessons/'.$lesson->id.'/media', ['kind' => 'video', 'file' => UploadedFile::fake()->create('preview.webm', 50, 'video/webm')])->json('data');

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/media/'.$asset['id'].'/preview')->assertOk()->assertJsonStructure(['data' => ['url']]);
        $this->getJson('/api/media/'.$asset['id'].'/stream')->assertForbidden();
    }

    public function test_preview_media_is_not_exposed_when_course_or_asset_is_unpublished(): void
    {
        Storage::fake('local');
        $course = $this->course('draft-preview-course');
        $course->update(['is_published' => false]);
        $lesson = $course->lessons()->create(['slug' => 'preview', 'title' => 'تجريبي', 'title_en' => 'Preview', 'duration_minutes' => 5, 'position' => 1, 'is_preview' => true, 'status' => 'published']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $asset = $this->postJson('/api/admin/courses/'.$course->id.'/lessons/'.$lesson->id.'/media', ['kind' => 'video', 'file' => UploadedFile::fake()->create('preview.webm', 50, 'video/webm')])->json('data');

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/media/'.$asset['id'].'/preview')->assertNotFound();
        $course->update(['is_published' => true]);
        $lesson->mediaAssets()->whereKey($asset['id'])->update(['status' => 'draft']);
        $this->getJson('/api/media/'.$asset['id'].'/preview')->assertNotFound();
    }


    public function test_public_catalog_does_not_expose_non_preview_external_video_url(): void
    {
        $course = $this->course('hidden-url-course');
        $lesson = $course->lessons()->create(['slug' => 'paid', 'title' => 'مدفوع', 'title_en' => 'Paid', 'duration_minutes' => 10, 'position' => 1, 'video_url' => 'https://cdn.example.test/secret.mp4', 'transcript' => ['محتوى سري'], 'transcript_en' => ['Protected content']]);
        $lesson->mediaAssets()->create(['kind' => 'resource', 'disk' => 'local', 'path' => 'private/secret.pdf', 'original_name' => 'secret.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100]);

        $this->getJson('/api/courses')->assertOk()
            ->assertJsonPath('data.0.lessons.0.videoUrl', null)
            ->assertJsonPath('data.0.lessons.0.transcript', null)
            ->assertJsonPath('data.0.lessons.0.transcriptEn', null)
            ->assertJsonCount(0, 'data.0.lessons.0.media');
    }

    public function test_enrolled_student_receives_protected_lesson_content_in_course_payload(): void
    {
        $course = $this->course('enrolled-content-course');
        $lesson = $course->lessons()->create(['slug' => 'paid', 'title' => 'مدفوع', 'title_en' => 'Paid', 'duration_minutes' => 10, 'position' => 1, 'video_url' => 'https://cdn.example.test/secret.mp4', 'transcript' => ['محتوى الدرس'], 'transcript_en' => ['Lesson content']]);
        $lesson->mediaAssets()->create(['kind' => 'resource', 'disk' => 'local', 'path' => 'private/guide.pdf', 'original_name' => 'guide.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $student->enrollments()->create(['course_id' => $course->id, 'enrolled_at' => now()]);
        Sanctum::actingAs($student);

        $this->getJson('/api/courses/'.$course->id)->assertOk()
            ->assertJsonPath('data.lessons.0.videoUrl', 'https://cdn.example.test/secret.mp4')
            ->assertJsonPath('data.lessons.0.transcriptEn.0', 'Lesson content')
            ->assertJsonPath('data.lessons.0.media.0.name', 'guide.pdf');
    }

    public function test_media_upload_rejects_unsupported_files(): void
    {
        Storage::fake('local');
        $course = $this->course('bad-media-course');
        $lesson = $course->lessons()->create(['slug' => 'lesson', 'title' => 'درس', 'title_en' => 'Lesson', 'duration_minutes' => 10, 'position' => 1]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/admin/courses/'.$course->id.'/lessons/'.$lesson->id.'/media', ['kind' => 'video', 'file' => UploadedFile::fake()->create('malware.exe', 20, 'application/x-msdownload')])->assertUnprocessable();
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_course_resource_upload_rejects_dangerous_extensions_even_with_allowed_mime(): void
    {
        Storage::fake('local');
        $course = $this->course('bad-resource-course');
        $lesson = $course->lessons()->create(['slug' => 'resource', 'title' => 'ملف', 'title_en' => 'Resource', 'duration_minutes' => 10, 'position' => 1]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        foreach (['php', 'phtml', 'html', 'svg', 'js', 'exe', 'sh'] as $extension) {
            $this->postJson('/api/admin/courses/'.$course->id.'/lessons/'.$lesson->id.'/media', [
                'kind' => 'resource',
                'file' => UploadedFile::fake()->create("resource.{$extension}", 10, 'application/pdf'),
            ])->assertUnprocessable();
        }

        $this->assertDatabaseCount('media_assets', 0);
    }

    private function course(string $id = 'media-course'): Course
    {
        return Course::create(['id' => $id, 'title' => 'دورة', 'title_en' => 'Course', 'summary' => 'ملخص', 'summary_en' => 'Summary', 'category' => 'Programming', 'grade' => 'grade-1', 'semester' => 'semester-1', 'level' => 'Beginner', 'duration_minutes' => 60, 'price' => 100, 'instructor' => 'أحمد', 'instructor_en' => 'Ahmed', 'is_published' => true]);
    }
}
