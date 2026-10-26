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
class AutoScreenResponseTest extends TestCase {
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
    private function submitBooking(string $key) {
        return $this->actingAs($this->user(UserRole::Patient), 'sanctum')->postJson('/api/v1/bookings', [
            'service_id' => $this->svc->id, 'area_id' => $this->area->id, 'scheduled_start' => $this->when->toIso8601String(),
            'address_line' => 'Jl. Uji No. 1', 'contact_phone' => '081234567890',
            'latitude' => $this->clat, 'longitude' => $this->clng], ['Idempotency-Key' => $key]);
    }
    private function history(string $bookingId): array {
        return DB::table('booking_status_histories')->where('booking_id', $bookingId)
            ->orderBy('occurred_at')->orderBy('id')->pluck('to_status')->all();
    }
    public function test_response_shows_latest_status_and_history_starts_with_requested(): void {
        config(['taran.auto_screen' => true]);
        $this->partner();
        $res = $this->submitBooking('resp-key-0001')->assertCreated();
        $res->assertJsonPath('data.status', 'OFFERED');
        $b = Booking::query()->firstOrFail();
        $h = $this->history($b->id);
        $this->assertSame('REQUESTED', $h[0]);
        $this->assertSame('OFFERED', end($h));
    }
    public function test_switch_off_response_is_requested_with_single_history_row(): void {
        $this->partner();
        $res = $this->submitBooking('resp-key-0002')->assertCreated();
        $res->assertJsonPath('data.status', 'REQUESTED');
        $b = Booking::query()->firstOrFail();
        $this->assertSame(['REQUESTED'], $this->history($b->id));
    }
}
