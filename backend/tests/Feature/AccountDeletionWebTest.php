<?php
namespace Tests\Feature;
use Tests\TestCase;
class AccountDeletionWebTest extends TestCase {
    public function test_deletion_page_loads_without_login(): void {
        $this->get('/hapus-akun')->assertOk()
            ->assertSee('Hapus akun')
            ->assertSee('/js/hapus-akun.js', false)
            ->assertSee('permanen');
        $this->assertFileExists(public_path('js/hapus-akun.js'));
    }
    public function test_deletion_page_has_no_third_party_resources(): void {
        $html = $this->get('/hapus-akun')->assertOk()->getContent();
        $this->assertStringNotContainsString('unpkg.com', $html);
        $this->assertStringNotContainsString('openstreetmap', $html);
    }
}
