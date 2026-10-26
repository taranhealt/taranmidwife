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
class OfferDispatchTest extends TestCase {
    use RefreshDatabase;
    private Service $svc;
    private Area $area;
    private Carbon $when;
    protected function setUp(): void {
        parent::setUp();
        $this->svc = Service::forceCreate(['id' => (string) Str::ulid(), 'code' => 'T-' . Str::random(6), 'name' => 'Layanan Uji',
            'price_amount' => 100000, 'active' => true, 'description' => '[DATA UJI]', 'limitations' => 'Uji']);
        $this->area = $this->newArea();
        $this->when = Carbon::now('Asia/Jakarta')->addDays(3)->setTime(10, 0, 0);
    }
    private function newArea(): Area {
        return Area::forceCreate(['id' => (string) Str::ulid(), 'name' => 'Area ' . Str::random(6), 'active' => true]);
    }
    private function user(UserRole $role): User {
        $u = User::factory()->create();
        $u->forceFill(['role' => $role])->save();
        return $u;
    }
    private function partner(array $o = []): User {
        $u = $this->user(UserRole::Partner);
        $p = PartnerProfile::create(['user_id' => $u->id, 'verification_status' => 'ACTIVE']);
        $p->forceFill(['sip_expires_on' => now()->addYear()->toDateString(), 'accepting_requests' => true,
            'last_offered_at' => $o['last'] ?? null])->save();
        DB::table('partner_services')->insert(['partner_profile_id' => $p->id, 'service_id' => $this->svc->id]);
        DB::table('partner_areas')->insert(['partner_profile_id' => $p->id, 'area_id' => $this->area->id]);
        DB::table('partner_availabilities')->insert(['partner_profile_id' => $p->id, 'weekday' => $this->when->dayOfWeek, 'hour' => 10]);
        return $u;
    }
    private function booking(string $status, ?Carbon $start = null, ?Area $area = null): Booking {
        return Booking::forceCreate(['id' => (string) Str::ulid(), 'code' => 'TM-' . strtoupper(Str::random(8)),
            'patient_id' => $this->user(UserRole::Patient)->id, 'service_id' => $this->svc->id,
            'area_id' => ($area ?? $this->area)->id, 'status' => $status,
            'scheduled_start' => ($start ?? $this->when)->copy()->utc(), 'price_amount' => 100000,
            'address_line' => 'Jl. Uji No. 1', 'landmark' => null, 'contact_phone' => '081234567890',
            'idempotency_key' => Str::random(20)]);
    }
    private function offer(Booking $b, User $p, bool $auto, ?string $expires = null): void {
        DB::table('assignments')->insert(['id' => (string) Str::ulid(), 'booking_id' => $b->id, 'partner_id' => $p->id,
            'scheduled_start' => $b->scheduled_start, 'active' => true, 'auto_offered' => $auto,
            'offer_expires_at' => $expires, 'created_at' => now(), 'updated_at' => now()]);
    }
    private function statusOf(Booking $b): string {
        return (string) DB::table('bookings')->where('id', $b->id)->value('status');
    }
    private function activePartner(Booking $b): ?int {
        $v = DB::table('assignments')->where('booking_id', $b->id)->where('active', true)->value('partner_id');
        return $v === null ? null : (int) $v;
    }
    public function test_screen_offers_to_candidate(): void {
        $p = $this->partner();
        $b = $this->booking('REQUESTED');
        $this->actingAs($this->user(UserRole::OpsAdmin), 'sanctum')->postJson("/api/v1/admin/bookings/{$b->id}/screen")
            ->assertOk()->assertJsonPath('data.status', 'OFFERED');
        $this->assertSame($p->id, $this->activePartner($b));
        $this->assertSame(1, DB::table('assignments')->where('booking_id', $b->id)->where('auto_offered', true)
            ->whereNotNull('offer_expires_at')->count());
        $this->assertNotNull(DB::table('partner_profiles')->where('user_id', $p->id)->value('last_offered_at'));
        $this->assertDatabaseHas('audit_events', ['action' => 'booking.auto_offer', 'entity_id' => $b->id]);
    }
    public function test_screen_without_candidate_stays_waiting(): void {
        $b = $this->booking('REQUESTED');
        $this->actingAs($this->user(UserRole::OpsAdmin), 'sanctum')->postJson("/api/v1/admin/bookings/{$b->id}/screen")
            ->assertOk()->assertJsonPath('data.status', 'WAITING_MIDWIFE');
        $this->assertNull($this->activePartner($b));
    }
    public function test_decline_moves_to_next_then_unfulfilled(): void {
        $p1 = $this->partner();
        $p2 = $this->partner(['last' => now()->subDay()->toDateTimeString()]);
        $b = $this->booking('REQUESTED');
        $this->actingAs($this->user(UserRole::OpsAdmin), 'sanctum')->postJson("/api/v1/admin/bookings/{$b->id}/screen")->assertOk();
        $this->assertSame($p1->id, $this->activePartner($b));
        $this->actingAs($p1, 'sanctum')->postJson("/api/v1/midwife/bookings/{$b->id}/decline")->assertOk();
        $this->assertSame('OFFERED', $this->statusOf($b));
        $this->assertSame($p2->id, $this->activePartner($b));
        $this->actingAs($p2, 'sanctum')->postJson("/api/v1/midwife/bookings/{$b->id}/decline")->assertOk();
        $this->assertSame('UNFULFILLED', $this->statusOf($b));
        $this->assertNull($this->activePartner($b));
    }
    public function test_decline_of_manual_offer_without_candidate_stays_waiting(): void {
        $p = $this->partner();
        $b = $this->booking('OFFERED');
        $this->offer($b, $p, false);
        $this->actingAs($p, 'sanctum')->postJson("/api/v1/midwife/bookings/{$b->id}/decline")->assertOk();
        $this->assertSame('WAITING_MIDWIFE', $this->statusOf($b));
        $this->assertNull($this->activePartner($b));
    }
    public function test_expired_offer_moves_to_next_candidate(): void {
        $p1 = $this->partner();
        $p2 = $this->partner(['last' => now()->subDay()->toDateTimeString()]);
        $b = $this->booking('OFFERED');
        $this->offer($b, $p1, true, now()->subMinute()->toDateTimeString());
        $this->artisan('bookings:expire-offers')->assertExitCode(0);
        $this->assertSame('OFFERED', $this->statusOf($b));
        $this->assertSame($p2->id, $this->activePartner($b));
        $this->assertSame(0, DB::table('assignments')->where('booking_id', $b->id)->where('partner_id', $p1->id)->where('active', true)->count());
    }
    public function test_expired_offer_without_other_candidate_is_unfulfilled(): void {
        $p1 = $this->partner();
        $b = $this->booking('OFFERED');
        $this->offer($b, $p1, true, now()->subMinute()->toDateTimeString());
        $this->artisan('bookings:expire-offers')->assertExitCode(0);
        $this->assertSame('UNFULFILLED', $this->statusOf($b));
        $this->assertNull($this->activePartner($b));
    }
    public function test_unexpired_offer_is_untouched(): void {
        $p1 = $this->partner();
        $b = $this->booking('OFFERED');
        $this->offer($b, $p1, true, now()->addMinutes(5)->toDateTimeString());
        $this->artisan('bookings:expire-offers')->assertExitCode(0);
        $this->assertSame('OFFERED', $this->statusOf($b));
        $this->assertSame($p1->id, $this->activePartner($b));
    }
    public function test_sweep_offers_or_closes_waiting_bookings(): void {
        $p = $this->partner();
        $future = $this->booking('WAITING_MIDWIFE');
        $past = $this->booking('WAITING_MIDWIFE', Carbon::now('Asia/Jakarta')->subDay()->setTime(10, 0, 0));
        $noMatch = $this->booking('WAITING_MIDWIFE', null, $this->newArea());
        $this->artisan('bookings:expire-offers')->assertExitCode(0);
        $this->assertSame('OFFERED', $this->statusOf($future));
        $this->assertSame($p->id, $this->activePartner($future));
        $this->assertSame('UNFULFILLED', $this->statusOf($past));
        $this->assertSame('WAITING_MIDWIFE', $this->statusOf($noMatch));
    }
}
