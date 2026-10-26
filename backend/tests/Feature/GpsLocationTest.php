<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Booking;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\User;
use App\Services\PartnerMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
class GpsLocationTest extends TestCase {
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
    private function partner(?array $point = null): User {
        $u = $this->user(UserRole::Partner);
        $p = PartnerProfile::create(['user_id' => $u->id, 'verification_status' => 'ACTIVE']);
        $fill = ['sip_expires_on' => now()->addYear()->toDateString(), 'accepting_requests' => true];
        if ($point) { $fill += ['base_lat' => $point[0], 'base_lng' => $point[1], 'service_radius_km' => $point[2]]; }
        $p->forceFill($fill)->save();
        DB::table('partner_services')->insert(['partner_profile_id' => $p->id, 'service_id' => $this->svc->id]);
        DB::table('partner_areas')->insert(['partner_profile_id' => $p->id, 'area_id' => $this->area->id]);
        DB::table('partner_availabilities')->insert(['partner_profile_id' => $p->id, 'weekday' => $this->when->dayOfWeek, 'hour' => 10]);
        return $u;
    }
    private function booking(bool $withGps): Booking {
        $a = ['id' => (string) Str::ulid(), 'code' => 'TM-' . strtoupper(Str::random(8)),
            'patient_id' => $this->user(UserRole::Patient)->id, 'service_id' => $this->svc->id, 'area_id' => $this->area->id,
            'status' => 'WAITING_MIDWIFE', 'scheduled_start' => $this->when->copy()->utc(), 'price_amount' => 100000,
            'address_line' => 'Jl. Uji No. 1', 'landmark' => null, 'contact_phone' => '081234567890',
            'idempotency_key' => Str::random(20)];
        if ($withGps) { $a['patient_lat'] = (string) $this->clat; $a['patient_lng'] = (string) $this->clng; }
        return Booking::forceCreate($a);
    }
    private function ids(Booking $b): array {
        return array_map('intval', array_column(app(PartnerMatcher::class)->candidates($b), 'partner_id'));
    }
    private function payload(array $extra = []): array {
        return array_merge(['service_id' => $this->svc->id, 'area_id' => $this->area->id,
            'scheduled_start' => $this->when->toIso8601String(), 'address_line' => 'Jl. Uji No. 1',
            'contact_phone' => '081234567890'], $extra);
    }
    public function test_booking_with_gps_stores_encrypted_coordinates(): void {
        $this->actingAs($this->user(UserRole::Patient), 'sanctum')
            ->postJson('/api/v1/bookings', $this->payload(['latitude' => $this->clat, 'longitude' => $this->clng]), ['Idempotency-Key' => 'gps-key-0001'])
            ->assertCreated();
        $raw = (string) DB::table('bookings')->value('patient_lat');
        $this->assertNotSame((string) $this->clat, $raw);
        $this->assertEqualsWithDelta($this->clat, (float) Booking::query()->firstOrFail()->patient_lat, 0.000001);
    }
    public function test_booking_outside_pilot_area_is_rejected(): void {
        $this->actingAs($this->user(UserRole::Patient), 'sanctum')
            ->postJson('/api/v1/bookings', $this->payload(['latitude' => -6.2, 'longitude' => 106.8]), ['Idempotency-Key' => 'gps-key-0002'])
            ->assertStatus(422)->assertJsonPath('code', 'OUT_OF_COVERAGE');
        $this->assertDatabaseCount('bookings', 0);
    }
    public function test_latitude_without_longitude_is_rejected(): void {
        $this->actingAs($this->user(UserRole::Patient), 'sanctum')
            ->postJson('/api/v1/bookings', $this->payload(['latitude' => $this->clat]), ['Idempotency-Key' => 'gps-key-0003'])
            ->assertStatus(422)->assertJsonValidationErrors('longitude');
        $this->assertDatabaseCount('bookings', 0);
    }
    public function test_matcher_filters_by_radius_and_orders_nearest_first(): void {
        $nearest = $this->partner([$this->clat + 0.02, $this->clng, 5]);
        $near = $this->partner([$this->clat + 0.05, $this->clng, 10]);
        $this->partner([$this->clat + 0.2, $this->clng, 10]);
        $b = $this->booking(true);
        $this->assertSame([$nearest->id, $near->id], $this->ids($b));
        $cand = app(PartnerMatcher::class)->candidates($b);
        $this->assertEqualsWithDelta(2.2, $cand[0]['distance_km'], 0.2);
        $this->assertEqualsWithDelta(5.6, $cand[1]['distance_km'], 0.2);
    }
    public function test_booking_without_gps_keeps_area_based_matching(): void {
        $far = $this->partner([$this->clat + 0.2, $this->clng, 10]);
        $none = $this->partner(null);
        $b = $this->booking(false);
        $this->assertSame([$far->id, $none->id], $this->ids($b));
    }
    public function test_partner_without_service_point_is_excluded_when_booking_has_gps(): void {
        $this->partner(null);
        $yes = $this->partner([$this->clat, $this->clng, 5]);
        $b = $this->booking(true);
        $this->assertSame([$yes->id], $this->ids($b));
    }
    public function test_coverage_endpoint(): void {
        $this->partner([$this->clat + 0.02, $this->clng, 5]);
        $patient = $this->user(UserRole::Patient);
        $this->getJson('/api/v1/coverage?lat=3.2&lng=99.5')->assertStatus(401);
        $this->actingAs($patient, 'sanctum')
            ->getJson("/api/v1/coverage?lat={$this->clat}&lng={$this->clng}&service_id={$this->svc->id}")
            ->assertOk()->assertJsonPath('data.in_pilot_area', true)
            ->assertJsonPath('data.partners_nearby', 1)->assertJsonPath('data.serviceable', true);
        $this->actingAs($patient, 'sanctum')->getJson('/api/v1/coverage?lat=-6.2&lng=106.8')
            ->assertOk()->assertJsonPath('data.in_pilot_area', false)->assertJsonPath('data.serviceable', false);
        $this->actingAs($this->user(UserRole::Partner), 'sanctum')
            ->getJson("/api/v1/coverage?lat={$this->clat}&lng={$this->clng}")->assertStatus(403);
    }
    public function test_partner_manages_service_point(): void {
        $u = $this->user(UserRole::Partner);
        PartnerProfile::create(['user_id' => $u->id, 'verification_status' => 'DRAFT']);
        $a = $this->actingAs($u, 'sanctum');
        $a->putJson('/api/v1/midwife/offerings', ['service_point' => ['lat' => 3.2, 'lng' => 99.5, 'radius_km' => 10]])
            ->assertOk()->assertJsonPath('data.service_point.radius_km', 10);
        $this->assertSame(1, DB::table('partner_profiles')->whereNotNull('base_lat')->count());
        $a->putJson('/api/v1/midwife/offerings', ['service_point' => ['lat' => 3.2, 'lng' => 99.5, 'radius_km' => 0]])->assertStatus(422);
        $a->putJson('/api/v1/midwife/offerings', ['service_point' => ['lat' => 3.2]])->assertStatus(422);
        $a->putJson('/api/v1/midwife/offerings', ['service_point' => ['lat' => 95, 'lng' => 99.5, 'radius_km' => 5]])->assertStatus(422);
        $this->getJson('/api/v1/midwife/offerings')->assertOk()->assertJsonPath('data.service_point.radius_km', 10);
        $a->putJson('/api/v1/midwife/offerings', ['service_point' => null])->assertOk()->assertJsonPath('data.service_point', null);
        $this->assertSame(0, DB::table('partner_profiles')->whereNotNull('base_lat')->count());
    }
    public function test_partner_sees_distance_but_not_coordinates(): void {
        $p = $this->partner([$this->clat + 0.02, $this->clng, 5]);
        $b = $this->booking(true);
        $this->actingAs($this->user(UserRole::OpsAdmin), 'sanctum')->postJson("/api/v1/admin/bookings/{$b->id}/assign", ['partner_id' => $p->id])->assertOk();
        $res = $this->actingAs($p, 'sanctum')->getJson('/api/v1/bookings')->assertOk()
            ->assertJsonPath('data.0.status', 'OFFERED')->assertJsonMissingPath('data.0.patient_lat')
            ->assertJsonMissingPath('data.0.address_line');
        $this->assertEqualsWithDelta(2.2, (float) $res->json('data.0.distance_km'), 0.2);
    }
}
