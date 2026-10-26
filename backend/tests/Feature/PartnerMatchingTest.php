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
class PartnerMatchingTest extends TestCase {
    use RefreshDatabase;
    private Service $svc;
    private Area $area;
    private Carbon $when;
    protected function setUp(): void {
        parent::setUp();
        $this->svc = $this->newService();
        $this->area = $this->newArea();
        $this->when = Carbon::now('Asia/Jakarta')->addDays(3)->setTime(10, 0, 0);
    }
    private function newService(bool $active = true): Service {
        return Service::forceCreate(['id' => (string) Str::ulid(), 'code' => 'T-' . Str::random(6), 'name' => 'Layanan Uji',
            'price_amount' => 100000, 'active' => $active, 'description' => '[DATA UJI]', 'limitations' => 'Uji']);
    }
    private function newArea(bool $active = true): Area {
        return Area::forceCreate(['id' => (string) Str::ulid(), 'name' => 'Area ' . Str::random(6), 'active' => $active]);
    }
    private function user(UserRole $role): User {
        $u = User::factory()->create();
        $u->forceFill(['role' => $role])->save();
        return $u;
    }
    private function partner(array $o = []): User {
        $u = $this->user(UserRole::Partner);
        $p = PartnerProfile::create(['user_id' => $u->id, 'verification_status' => $o['status'] ?? 'ACTIVE']);
        $p->forceFill(['sip_expires_on' => $o['sip'] ?? now()->addYear()->toDateString(),
            'accepting_requests' => $o['accepting'] ?? true, 'last_offered_at' => $o['last'] ?? null])->save();
        DB::table('partner_services')->insert(['partner_profile_id' => $p->id, 'service_id' => ($o['service'] ?? $this->svc)->id]);
        DB::table('partner_areas')->insert(['partner_profile_id' => $p->id, 'area_id' => ($o['area'] ?? $this->area)->id]);
        DB::table('partner_availabilities')->insert(['partner_profile_id' => $p->id,
            'weekday' => $o['weekday'] ?? $this->when->dayOfWeek, 'hour' => $o['hour'] ?? 10]);
        if (isset($o['user_status'])) { $u->forceFill(['status' => $o['user_status']])->save(); }
        return $u;
    }
    private function booking(User $patient, string $status = 'WAITING_MIDWIFE'): Booking {
        return Booking::forceCreate(['id' => (string) Str::ulid(), 'code' => 'TM-' . strtoupper(Str::random(8)),
            'patient_id' => $patient->id, 'service_id' => $this->svc->id, 'area_id' => $this->area->id, 'status' => $status,
            'scheduled_start' => $this->when->copy()->utc(), 'price_amount' => 100000, 'address_line' => 'Jl. Uji No. 1',
            'landmark' => null, 'contact_phone' => '081234567890', 'idempotency_key' => Str::random(20)]);
    }
    private function assign(Booking $b, User $partner, bool $active): void {
        DB::table('assignments')->insert(['id' => (string) Str::ulid(), 'booking_id' => $b->id, 'partner_id' => $partner->id,
            'scheduled_start' => $b->scheduled_start, 'active' => $active, 'created_at' => now(), 'updated_at' => now()]);
    }
    private function ids(Booking $b, array $exclude = []): array {
        return array_column(app(PartnerMatcher::class)->candidates($b, $exclude), 'partner_id');
    }
    public function test_partner_manages_offerings(): void {
        $u = $this->user(UserRole::Partner);
        PartnerProfile::create(['user_id' => $u->id, 'verification_status' => 'DRAFT']);
        $this->actingAs($u, 'sanctum')->putJson('/api/v1/midwife/offerings', [
            'service_ids' => [$this->svc->id], 'area_ids' => [$this->area->id],
            'availability' => [['weekday' => 1, 'hours' => [8, 10]], ['weekday' => 3, 'hours' => [14]]],
            'accepting_requests' => false,
        ])->assertOk()->assertJsonPath('data.service_ids.0', $this->svc->id)
            ->assertJsonPath('data.area_ids.0', $this->area->id)
            ->assertJsonPath('data.accepting_requests', false)
            ->assertJsonCount(2, 'data.availability')
            ->assertJsonPath('data.availability.0.hours', [8, 10]);
        $this->getJson('/api/v1/midwife/offerings')->assertOk()->assertJsonCount(2, 'data.availability');
        $this->putJson('/api/v1/midwife/offerings', ['availability' => [], 'accepting_requests' => true])->assertOk()
            ->assertJsonCount(0, 'data.availability')->assertJsonPath('data.accepting_requests', true)
            ->assertJsonPath('data.service_ids.0', $this->svc->id);
        $this->assertDatabaseHas('audit_events', ['action' => 'partner.offerings.update']);
    }
    public function test_offerings_validation_and_roles(): void {
        $this->getJson('/api/v1/midwife/offerings')->assertStatus(401);
        $this->actingAs($this->user(UserRole::Patient), 'sanctum')->getJson('/api/v1/midwife/offerings')->assertStatus(403);
        $u = $this->user(UserRole::Partner);
        PartnerProfile::create(['user_id' => $u->id, 'verification_status' => 'DRAFT']);
        $a = $this->actingAs($u, 'sanctum');
        $a->putJson('/api/v1/midwife/offerings', ['service_ids' => [str_repeat('0', 26)]])->assertStatus(422);
        $a->putJson('/api/v1/midwife/offerings', ['service_ids' => [$this->newService(false)->id]])->assertStatus(422);
        $a->putJson('/api/v1/midwife/offerings', ['area_ids' => [$this->newArea(false)->id]])->assertStatus(422);
        $a->putJson('/api/v1/midwife/offerings', ['availability' => [['weekday' => 1, 'hours' => [9]]]])->assertStatus(422);
        $a->putJson('/api/v1/midwife/offerings', ['availability' => [['weekday' => 7, 'hours' => [8]]]])->assertStatus(422);
        $a->putJson('/api/v1/midwife/offerings', ['accepting_requests' => 'mungkin'])->assertStatus(422);
        $this->assertSame(0, DB::table('partner_services')->count());
        $this->assertSame(0, DB::table('partner_availabilities')->count());
    }
    public function test_each_criterion_excludes_partner(): void {
        $b = $this->booking($this->user(UserRole::Patient));
        $ok = $this->partner();
        $this->partner(['status' => 'SUSPENDED']);
        $this->partner(['status' => 'APPROVED']);
        $this->partner(['sip' => now()->subDay()->toDateString()]);
        $this->partner(['accepting' => false]);
        $this->partner(['user_status' => 'suspended']);
        $this->partner(['service' => $this->newService()]);
        $this->partner(['area' => $this->newArea()]);
        $this->partner(['hour' => 12]);
        $this->partner(['weekday' => ($this->when->dayOfWeek + 1) % 7]);
        $this->assertSame([$ok->id], array_map('intval', $this->ids($b)));
    }
    public function test_busy_slot_and_tried_partner_are_excluded(): void {
        $b = $this->booking($this->user(UserRole::Patient));
        $other = $this->booking($this->user(UserRole::Patient), 'CONFIRMED');
        $busy = $this->partner();
        $freed = $this->partner();
        $tried = $this->partner();
        $ok = $this->partner();
        $this->assign($other, $busy, true);
        $this->assign($other, $freed, false);
        $this->assign($b, $tried, false);
        $got = array_map('intval', $this->ids($b));
        sort($got);
        $expected = [$freed->id, $ok->id];
        sort($expected);
        $this->assertSame($expected, $got);
    }
    public function test_fair_ordering_and_explicit_exclusion(): void {
        $b = $this->booking($this->user(UserRole::Patient));
        $recent = $this->partner(['last' => now()->subHour()->toDateTimeString()]);
        $never = $this->partner();
        $older = $this->partner(['last' => now()->subDay()->toDateTimeString()]);
        $this->assertSame([$never->id, $older->id, $recent->id], array_map('intval', $this->ids($b)));
        $this->assertSame([$older->id, $recent->id], array_map('intval', $this->ids($b, [$never->id])));
    }
    public function test_admin_candidates_endpoint(): void {
        $b = $this->booking($this->user(UserRole::Patient));
        $ok = $this->partner();
        $this->partner(['accepting' => false]);
        $this->actingAs($this->user(UserRole::OpsAdmin), 'sanctum')->getJson("/api/v1/admin/bookings/{$b->id}/candidates")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.partner_id', $ok->id)
            ->assertJsonPath('meta.count', 1);
        $this->assertDatabaseHas('audit_events', ['action' => 'booking.candidates.view', 'entity_id' => $b->id]);
    }
    public function test_admin_candidates_permissions_and_status(): void {
        $b = $this->booking($this->user(UserRole::Patient));
        $this->getJson("/api/v1/admin/bookings/{$b->id}/candidates")->assertStatus(401);
        foreach ([UserRole::Patient, UserRole::Partner, UserRole::Reviewer, UserRole::SysAdmin] as $role) {
            $this->actingAs($this->user($role), 'sanctum')->getJson("/api/v1/admin/bookings/{$b->id}/candidates")->assertStatus(403);
        }
        $ops = $this->actingAs($this->user(UserRole::OpsAdmin), 'sanctum');
        $done = $this->booking($this->user(UserRole::Patient), 'COMPLETED');
        $ops->getJson("/api/v1/admin/bookings/{$done->id}/candidates")->assertStatus(409)->assertJsonPath('code', 'BOOKING_NOT_MATCHABLE');
        $ops->getJson('/api/v1/admin/bookings/' . (string) Str::ulid() . '/candidates')->assertStatus(404);
    }
    public function test_booking_created_through_api_is_matched_by_wib_slot(): void {
        $ok = $this->partner();
        $this->actingAs($this->user(UserRole::Patient), 'sanctum')->postJson('/api/v1/bookings', [
            'service_id' => $this->svc->id, 'area_id' => $this->area->id,
            'scheduled_start' => $this->when->toIso8601String(),
            'address_line' => 'Jl. Uji No. 1', 'contact_phone' => '081234567890',
        ], ['Idempotency-Key' => 'e2e-key-12345'])->assertCreated();
        $b = Booking::query()->firstOrFail();
        $this->assertSame([$ok->id], array_map('intval', $this->ids($b)));
    }
}
