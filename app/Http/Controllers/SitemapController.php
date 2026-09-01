<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseBundle;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $static = ['/', '/courses', '/bundles', '/subscriptions', '/about', '/faq', '/contact', '/terms', '/privacy', '/certificates/verify'];
        $urls = collect($static)->map(fn (string $path) => [
            'location' => $frontend.$path,
            'lastmod' => null,
            'priority' => $path === '/' ? '1.0' : '0.7',
        ]);

        Course::query()->available()->select(['id', 'updated_at'])->orderBy('id')->get()
            ->each(fn (Course $course) => $urls->push([
                'location' => "{$frontend}/courses/{$course->id}",
                'lastmod' => $course->updated_at?->toAtomString(),
                'priority' => '0.8',
            ]));

        CourseBundle::query()->where('is_active', true)->select(['id', 'updated_at'])->orderBy('id')->get()
            ->each(fn (CourseBundle $bundle) => $urls->push([
                'location' => "{$frontend}/bundles/{$bundle->id}",
                'lastmod' => $bundle->updated_at?->toAtomString(),
                'priority' => '0.7',
            ]));

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
