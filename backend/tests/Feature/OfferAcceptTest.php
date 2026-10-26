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
class OfferAcceptTest extends TestCase {
    use RefreshDatabase;
    private function makeUser(UserRole $role): User {
        $u = User::factory()->create();
        $u->forceFill(['role' => $role])->save();
        return $u;
    }
    private function offered(bool $auto, ?string $expires): array {
        $svc = Service::forceCreate(['id' => (string) Str::ulid(), 'code' => 'T-' . Str::random(6), 'name' => 'Layanan Uji',
            'price_amount' => 100000, 'active' => true, 'description' => '[DATA UJI]', 'limitations' => 'Uji']);
        $area = Area::forceCreate(['id' => (string) Str::ulid(), 'name' => 'Area ' . Str::random(6), 'active' => true]);
        $partner = $this->makeUser(UserRole::Partner);
        PartnerProfile::create(['user_id' => $partner->id, 'verification_status' => 'ACTIVE']);
        $b = Booking::forceCreate(['id' => (string) Str::ulid(), 'code' => 'TM-' . strtoupper(Str::random(8)),
            'patient_id' => $this->makeUser(UserRole::Patient)->id, 'service_id' => $svc->id, 'area_id' => $area->id,
            'status' => 'OFFERED', 'scheduled_start' => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(10, 0, 0)->utc(),
            'price_amount' => 100000, 'address_line' => 'Jl. Uji No. 1', 'landmark' => null,
            'contact_phone' => '081234567890', 'idempotency_key' => Str::random(20)]);
        DB::table('assignments')->insert(['id' => (string) Str::ulid(), 'booking_id' => $b->id, 'partner_id' => $partner->id,
            'scheduled_start' => $b->scheduled_start, 'active' => true, 'auto_offered' => $auto,
            'offer_expires_at' => $expires, 'created_at' => now(), 'updated_at' => now()]);
        return [$b, $partner];
    }
    private function bookingStatus(Booking $b): string {
        return (string) DB::table('bookings')->where('id', $b->id)->value('status');
    }
    public function test_expired_auto_offer_cannot_be_accepted(): void {
        [$b, $p] = $this->offered(true, now()->subMinute()->toDateTimeString());
        $this->actingAs($p, 'sanctum')->postJson("/api/v1/midwife/bookings/{$b->id}/accept")
            ->assertStatus(409)->assertJsonPath('code', 'OFFER_EXPIRED');
        $this->assertSame('OFFERED', $this->bookingStatus($b));
        $this->assertSame(1, DB::table('assignments')->where('booking_id', $b->id)->where('active', true)->count());
    }
    public function test_unexpired_auto_offer_can_be_accepted(): void {
        [$b, $p] = $this->offered(true, now()->addMinutes(5)->toDateTimeString());
        $this->actingAs($p, 'sanctum')->postJson("/api/v1/midwife/bookings/{$b->id}/accept")
            ->assertOk()->assertJsonPath('data.status', 'CONFIRMED');
    }
    public function test_manual_offer_without_expiry_can_be_accepted(): void {
        [$b, $p] = $this->offered(false, null);
        $this->actingAs($p, 'sanctum')->postJson("/api/v1/midwife/bookings/{$b->id}/accept")
            ->assertOk()->assertJsonPath('data.status', 'CONFIRMED');
    }
    public function test_other_partner_still_gets_404(): void {
        [$b] = $this->offered(true, now()->subMinute()->toDateTimeString());
        $other = $this->makeUser(UserRole::Partner);
        PartnerProfile::create(['user_id' => $other->id, 'verification_status' => 'ACTIVE']);
        $this->actingAs($other, 'sanctum')->postJson("/api/v1/midwife/bookings/{$b->id}/accept")->assertStatus(404);
    }
}
