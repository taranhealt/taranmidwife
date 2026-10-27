<?php
namespace Tests\Feature;
use Tests\TestCase;
class HomePageTest extends TestCase {
    public function test_home_shows_taranmidwife_and_safe_links_only(): void {
        $r = $this->get('/');
        $r->assertOk()->assertSee('TaranMidwife')->assertSee('Tahap uji coba')
            ->assertSee('bukan layanan gawat darurat', false)
            ->assertSee('/app', false)->assertSee('/mitra', false)
            ->assertSee('/kebijakan-privasi', false)->assertSee('/hapus-akun', false)
            ->assertDontSee('/admin', false)->assertDontSee('Laravel', false);
    }
    public function test_home_has_no_third_party_resources(): void {
        $html = $this->get('/')->getContent();
        $this->assertDoesNotMatchRegularExpression('#(src|href)=["\']https?://#i', $html);
    }
}
