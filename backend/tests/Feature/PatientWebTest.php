<?php
namespace Tests\Feature;
use Tests\TestCase;
class PatientWebTest extends TestCase {
    public function test_patient_page_loads_with_assets(): void {
        $this->get('/app')->assertOk()
            ->assertSee('TaranMidwife')
            ->assertSee('/js/patient.js', false)
            ->assertSee('leaflet', false);
        $this->assertFileExists(public_path('js/patient.js'));
        $this->assertFileExists(public_path('css/patient.css'));
    }
    public function test_patient_page_uses_configured_pilot_center(): void {
        config(['taran.pilot.center_lat' => 1.5, 'taran.pilot.center_lng' => 100.25]);
        $this->get('/app')->assertOk()
            ->assertSee('data-lat="1.5"', false)
            ->assertSee('data-lng="100.25"', false);
    }
}
