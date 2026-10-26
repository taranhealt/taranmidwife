<?php
namespace Tests\Feature;
use Tests\TestCase;
class MitraWebTest extends TestCase {
    public function test_partner_page_loads_with_assets(): void {
        $this->get('/mitra')->assertOk()
            ->assertSee('TaranMidwife')
            ->assertSee('/js/mitra.js', false)
            ->assertSee('leaflet', false)
            ->assertSee('minlength="10"', false);
        $this->assertFileExists(public_path('js/mitra.js'));
    }
    public function test_patient_register_password_minlength_matches_server(): void {
        $this->get('/app')->assertOk()->assertSee('minlength="10"', false);
    }
}
