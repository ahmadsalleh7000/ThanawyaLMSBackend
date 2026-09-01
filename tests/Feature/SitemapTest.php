<?php

namespace Tests\Feature;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_public_pages_and_only_published_courses(): void
    {
        Course::factory()->create(['id' => 'public-course', 'is_published' => true]);
        Course::factory()->create(['id' => 'draft-course', 'is_published' => false]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk()->assertHeader('content-type', 'application/xml; charset=UTF-8');
        $response->assertSee('/courses/public-course', false);
        $response->assertDontSee('/courses/draft-course', false);
    }
}
