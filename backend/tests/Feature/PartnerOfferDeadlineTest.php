<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Booking;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
class PartnerOfferDeadlineTest extends TestCase {
    use RefreshDatabase;
    private Service $svc;
    private Area $area;
    private Carbon $when;
    private float $clat;
    private float $clng;
    protected function setUp(): void {
        parent::setUp();
        $this->svc = Service::forceCreate(['id' => (string) Str::ulid(), 'code' => 'T-' . Str::random(6), 'name' => 'Layanan Uji',
            'price_amount' => 100000, 'active' => true, 'description' => '[DATA UJI]', 'limitations' => 'Uji']);
        $this->area = Area::forceCreate(['id' => (string) Str::ulid(), 'name' => 'Area ' . Str::random(6), 'active' => true]);
        $this->when = Carbon::now('Asia/Jakarta')->addDays(3)->setTime(10, 0, 0);
        $this->clat = (float) config('taran.pilot.center_lat');
        $this->clng = (float) config('taran.pilot.center_lng');
    }
    private function user(UserRole $role): User {
        $u = User::factory()->create();
        $u->forceFill(['role' => $role])->save();
        return $u;
    }
    private function partner(): User {
        $u = $this->user(UserRole::Partner);
        $p = PartnerProfile::create(['user_id' => $u->id, 'verification_status' => 'ACTIVE']);
        $p->forceFill(['sip_expires_on' => now()->addYear()->toDateString(), 'accepting_requests' => true,
            'base_lat' => $this->clat + 0.02, 'base_lng' => $this->clng, 'service_radius_km' => 5])->save();
        DB::table('partner_services')->insert(['partner_profile_id' => $p->id, 'service_id' => $this->svc->id]);
        DB::table('partner_areas')->insert(['partner_profile_id' => $p->id, 'area_id' => $this->area->id]);
        DB::table('partner_availabilities')->insert(['partner_profile_id' => $p->id, 'weekday' => $this->when->dayOfWeek, 'hour' => 10]);
        return $u;
    }
    private function booking(): Booking {
        return Booking::forceCreate(['id' => (string) Str::ulid(), 'code' => 'TM-' . strtoupper(Str::random(8)),
            'patient_id' => $this->user(UserRole::Patient)->id, 'service_id' => $this->svc->id, 'area_id' => $this->area->id,
            'status' => 'WAITING_MIDWIFE', 'scheduled_start' => $this->when->copy()->utc(), 'price_amount' => 100000,
            'address_line' => 'Jl. Uji No. 1', 'landmark' => null, 'contact_phone' => '081234567890',
            'idempotency_key' => Str::random(20), 'patient_lat' => (string) $this->clat, 'patient_lng' => (string) $this->clng]);
    }
    public function test_auto_offer_shows_deadline_to_partner_only(): void {
        config(['taran.auto_screen' => true]);
        $p = $this->partner();
        $res = $this->actingAs($this->user(UserRole::Patient), 'sanctum')->postJson('/api/v1/bookings', [
            'service_id' => $this->svc->id, 'area_id' => $this->area->id, 'scheduled_start' => $this->when->toIso8601String(),
            'address_line' => 'Jl. Uji No. 1', 'contact_phone' => '081234567890',
            'latitude' => $this->clat, 'longitude' => $this->clng], ['Idempotency-Key' => 'dl-key-0001'])->assertCreated();
        $res->assertJsonPath('data.status', 'OFFERED')->assertJsonMissingPath('data.offer_expires_at');
        $list = $this->actingAs($p, 'sanctum')->getJson('/api/v1/bookings')->assertOk();
        $exp = $list->json('data.0.offer_expires_at');
        $this->assertNotNull($exp);
        $this->assertTrue(Carbon::parse($exp)->isFuture());
        $this->assertTrue(Carbon::parse($exp)->lte(now()->addMinutes(11)));
    }
    public function test_manual_assignment_has_no_deadline(): void {
        $p = $this->partner();
        $b = $this->booking();
        $this->actingAs($this->user(UserRole::OpsAdmin), 'sanctum')->postJson("/api/v1/admin/bookings/{$b->id}/assign", ['partner_id' => $p->id])->assertOk();
        $this->actingAs($p, 'sanctum')->getJson('/api/v1/bookings')->assertOk()
            ->assertJsonPath('data.0.status', 'OFFERED')->assertJsonMissingPath('data.0.offer_expires_at');
    }
}
