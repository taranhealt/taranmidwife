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
class AutoScreenTest extends TestCase {
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
    private function partner(array $point): User {
        $u = $this->user(UserRole::Partner);
        $p = PartnerProfile::create(['user_id' => $u->id, 'verification_status' => 'ACTIVE']);
        $p->forceFill(['sip_expires_on' => now()->addYear()->toDateString(), 'accepting_requests' => true,
            'base_lat' => $point[0], 'base_lng' => $point[1], 'service_radius_km' => $point[2]])->save();
        DB::table('partner_services')->insert(['partner_profile_id' => $p->id, 'service_id' => $this->svc->id]);
        DB::table('partner_areas')->insert(['partner_profile_id' => $p->id, 'area_id' => $this->area->id]);
        DB::table('partner_availabilities')->insert(['partner_profile_id' => $p->id, 'weekday' => $this->when->dayOfWeek, 'hour' => 10]);
        return $u;
    }
    private function submitBooking(string $key) {
        return $this->actingAs($this->user(UserRole::Patient), 'sanctum')->postJson('/api/v1/bookings', [
            'service_id' => $this->svc->id, 'area_id' => $this->area->id, 'scheduled_start' => $this->when->toIso8601String(),
            'address_line' => 'Jl. Uji No. 1', 'contact_phone' => '081234567890',
            'latitude' => $this->clat, 'longitude' => $this->clng], ['Idempotency-Key' => $key]);
    }
    public function test_switch_off_keeps_booking_requested(): void {
        $this->partner([$this->clat + 0.02, $this->clng, 5]);
        $this->submitBooking('auto-key-0001')->assertCreated();
        $this->assertSame('REQUESTED', Booking::query()->firstOrFail()->status);
        $this->assertSame(0, DB::table('assignments')->count());
    }
    public function test_switch_on_screens_and_offers_nearest_partner(): void {
        config(['taran.auto_screen' => true]);
        $p = $this->partner([$this->clat + 0.02, $this->clng, 5]);
        $this->submitBooking('auto-key-0002')->assertCreated();
        $b = Booking::query()->firstOrFail();
        $this->assertSame('OFFERED', $b->status);
        $this->assertSame(1, DB::table('assignments')->where('booking_id', $b->id)->where('partner_id', $p->id)
            ->where('active', true)->where('auto_offered', true)->count());
        $this->assertDatabaseHas('audit_events', ['action' => 'booking.auto_screen', 'outcome' => 'success']);
    }
    public function test_switch_on_without_candidates_waits_for_midwife(): void {
        config(['taran.auto_screen' => true]);
        $this->submitBooking('auto-key-0003')->assertCreated();
        $this->assertSame('WAITING_MIDWIFE', Booking::query()->firstOrFail()->status);
        $this->assertSame(0, DB::table('assignments')->count());
    }
}
