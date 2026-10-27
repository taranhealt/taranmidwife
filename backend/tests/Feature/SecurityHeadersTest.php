<?php
namespace Tests\Feature;
use Tests\TestCase;
class SecurityHeadersTest extends TestCase {
    public function test_security_headers_on_web_and_api(): void {
        foreach (['/', '/api/v1/services'] as $path) {
            $r = $this->get($path)->assertOk();
            $r->assertHeader('X-Content-Type-Options', 'nosniff');
            $r->assertHeader('X-Frame-Options', 'DENY');
            $r->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
    }
    public function test_hsts_only_in_production(): void {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
    }
}
