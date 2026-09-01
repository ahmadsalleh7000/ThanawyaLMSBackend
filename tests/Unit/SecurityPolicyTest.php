<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SecurityPolicyTest extends TestCase
{
    public function test_content_security_policy_keeps_scripts_restricted_to_the_application(): void
    {
        $policy = config('security.content_security_policy');

        $this->assertStringContainsString("script-src 'self'", $policy);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("frame-ancestors 'self'", $policy);
    }

    public function test_blade_views_do_not_use_inline_javascript_handlers(): void
    {
        $violations = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file) => preg_match('/\s(?:on[a-z]+)\s*=\s*["\']/i', $file->getContents()))
            ->map(fn ($file) => $file->getRelativePathname())
            ->values()
            ->all();

        $this->assertSame([], $violations, 'Inline event handlers violate the application CSP.');
    }
}
