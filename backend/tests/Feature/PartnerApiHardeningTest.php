<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
class PartnerApiHardeningTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); Storage::fake('local'); }
    private function partner(): User {
        $u = User::factory()->create();
        $u->forceFill(['role' => UserRole::Partner])->save();
        PartnerProfile::create(['user_id' => $u->id, 'verification_status' => 'DRAFT']);
        return $u;
    }
    private function service(): Service {
        return Service::forceCreate(['id' => (string) Str::ulid(), 'code' => 'H-' . Str::random(6), 'name' => 'Layanan Uji',
            'price_amount' => 100000, 'active' => true, 'description' => '[DATA UJI]', 'limitations' => 'Uji']);
    }
    private function area(): Area {
        return Area::forceCreate(['id' => (string) Str::ulid(), 'name' => 'Area ' . Str::random(6), 'active' => true]);
    }
    public function test_partners_cannot_see_or_change_each_others_offerings(): void {
        $a = $this->partner();
        $b = $this->partner();
        $svc = $this->service();
        $area = $this->area();
        $this->actingAs($a, 'sanctum')->putJson('/api/v1/midwife/offerings', [
            'service_ids' => [$svc->id], 'area_ids' => [$area->id], 'accepting_requests' => false,
            'service_point' => ['lat' => 3.58, 'lng' => 98.67, 'radius_km' => 10],
        ])->assertOk();
        $this->actingAs($b, 'sanctum')->getJson('/api/v1/midwife/offerings')->assertOk()
            ->assertJsonPath('data.service_ids', [])->assertJsonPath('data.area_ids', [])
            ->assertJsonPath('data.accepting_requests', true)->assertJsonPath('data.service_point', null);
        $this->actingAs($b, 'sanctum')->putJson('/api/v1/midwife/offerings', ['service_ids' => [], 'accepting_requests' => true])->assertOk();
        $this->actingAs($a, 'sanctum')->getJson('/api/v1/midwife/offerings')->assertOk()
            ->assertJsonPath('data.service_ids', [$svc->id])->assertJsonPath('data.area_ids', [$area->id])
            ->assertJsonPath('data.accepting_requests', false)->assertJsonPath('data.service_point.radius_km', 10);
    }
    public function test_service_point_validation_clear_and_partial_updates(): void {
        $u = $this->partner();
        $a = $this->actingAs($u, 'sanctum');
        $a->putJson('/api/v1/midwife/offerings', ['service_point' => ['lat' => 91, 'lng' => 98.6, 'radius_km' => 10]])->assertStatus(422);
        $a->putJson('/api/v1/midwife/offerings', ['service_point' => ['lat' => 3.5, 'lng' => 181, 'radius_km' => 10]])->assertStatus(422);
        $a->putJson('/api/v1/midwife/offerings', ['service_point' => ['lat' => 3.5, 'lng' => 98.6, 'radius_km' => 0]])->assertStatus(422);
        $a->putJson('/api/v1/midwife/offerings', ['service_point' => ['lat' => 3.5, 'lng' => 98.6, 'radius_km' => 101]])->assertStatus(422);
        $a->putJson('/api/v1/midwife/offerings', ['service_point' => ['lat' => 3.5, 'lng' => 98.6]])->assertStatus(422);
        $a->putJson('/api/v1/midwife/offerings', ['service_point' => ['lat' => 3.5, 'lng' => 98.6, 'radius_km' => 5, 'x' => 1]])->assertStatus(422);
        $this->assertNull(DB::table('partner_profiles')->value('base_lat'));
        $a->putJson('/api/v1/midwife/offerings', ['service_point' => ['lat' => 3.5, 'lng' => 98.6, 'radius_km' => 5]])
            ->assertOk()->assertJsonPath('data.service_point.radius_km', 5);
        $a->putJson('/api/v1/midwife/offerings', ['accepting_requests' => true])->assertOk()
            ->assertJsonPath('data.service_point.radius_km', 5)->assertJsonPath('data.accepting_requests', true);
        $a->putJson('/api/v1/midwife/offerings', ['service_point' => null])->assertOk()->assertJsonPath('data.service_point', null);
        $this->assertNull(DB::table('partner_profiles')->value('base_lat'));
        $this->assertNull(DB::table('partner_profiles')->value('service_radius_km'));
    }
    public function test_duplicate_ids_are_rejected(): void {
        $u = $this->partner();
        $svc = $this->service();
        $this->actingAs($u, 'sanctum')->putJson('/api/v1/midwife/offerings', ['service_ids' => [$svc->id, $svc->id]])->assertStatus(422);
        $this->assertSame(0, DB::table('partner_services')->count());
    }
    public function test_document_upload_is_rate_limited_separately_from_other_requests(): void {
        $u = $this->partner();
        $a = $this->actingAs($u, 'sanctum');
        for ($i = 0; $i < 25; $i++) { $a->getJson('/api/v1/midwife/profile')->assertOk(); }
        for ($i = 0; $i < 20; $i++) {
            $a->post('/api/v1/midwife/documents', ['type' => 'SIP', 'file' => UploadedFile::fake()->create('sip.pdf', 1, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated();
        }
        $a->post('/api/v1/midwife/documents', ['type' => 'SIP', 'file' => UploadedFile::fake()->create('sip.pdf', 1, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(429);
        $a->getJson('/api/v1/midwife/profile')->assertOk();
    }
}
