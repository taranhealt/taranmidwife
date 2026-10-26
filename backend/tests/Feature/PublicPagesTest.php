<?php
namespace Tests\Feature;
use Tests\TestCase;
class PublicPagesTest extends TestCase {
    public function test_forgot_password_page_loads(): void {
        $this->get('/lupa-sandi')->assertOk()->assertSee('Lupa kata sandi')->assertSee('/js/lupa-sandi.js', false);
        $this->assertFileExists(public_path('js/lupa-sandi.js'));
    }
    public function test_reset_password_page_loads(): void {
        $this->get('/atur-ulang-sandi?token=x&email=a@example.test')->assertOk()
            ->assertSee('Atur ulang kata sandi')->assertSee('/js/atur-ulang-sandi.js', false);
        $this->assertFileExists(public_path('js/atur-ulang-sandi.js'));
    }
    public function test_privacy_policy_is_marked_draft_and_links_to_deletion(): void {
        $this->get('/kebijakan-privasi')->assertOk()->assertSee('DRAF')->assertSee('/hapus-akun', false)->assertSee('[ISI]', false);
    }
    public function test_new_pages_have_no_third_party_resources(): void {
        foreach (['/lupa-sandi', '/atur-ulang-sandi', '/kebijakan-privasi'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertStringNotContainsString('unpkg.com', $html);
            $this->assertStringNotContainsString('openstreetmap', $html);
            $this->assertStringNotContainsString('googleapis', $html);
        }
    }
}
