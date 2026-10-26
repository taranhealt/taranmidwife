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
class AssignEligibilityTest extends TestCase {
    use RefreshDatabase;
    private Service $svc;
    private Area $area;
    private Carbon $when;
    protected function setUp(): void {
        parent::setUp();
        $this->svc = Service::forceCreate(['id' => (string) Str::ulid(), 'code' => 'T-' . Str::random(6), 'name' => 'Layanan Uji',
            'price_amount' => 100000, 'active' => true, 'description' => '[DATA UJI]', 'limitations' => 'Uji']);
        $this->area = Area::forceCreate(['id' => (string) Str::ulid(), 'name' => 'Area ' . Str::random(6), 'active' => true]);
        $this->when = Carbon::now('Asia/Jakarta')->addDays(3)->setTime(10, 0, 0);
    }
    private function user(UserRole $role): User {
        $u = User::factory()->create();
        $u->forceFill(['role' => $role])->save();
        return $u;
    }
    private function partner(bool $eligible = true, ?string $sip = null): User {
        $u = $this->user(UserRole::Partner);
        $p = PartnerProfile::create(['user_id' => $u->id, 'verification_status' => 'ACTIVE']);
        $p->forceFill(['sip_expires_on' => $sip ?? now()->addYear()->toDateString(), 'accepting_requests' => true])->save();
        if ($eligible) {
            DB::table('partner_services')->insert(['partner_profile_id' => $p->id, 'service_id' => $this->svc->id]);
            DB::table('partner_areas')->insert(['partner_profile_id' => $p->id, 'area_id' => $this->area->id]);
            DB::table('partner_availabilities')->insert(['partner_profile_id' => $p->id, 'weekday' => $this->when->dayOfWeek, 'hour' => 10]);
        }
        return $u;
    }
    private function waiting(): Booking {
        return Booking::forceCreate(['id' => (string) Str::ulid(), 'code' => 'TM-' . strtoupper(Str::random(8)),
            'patient_id' => $this->user(UserRole::Patient)->id, 'service_id' => $this->svc->id, 'area_id' => $this->area->id,
            'status' => 'WAITING_MIDWIFE', 'scheduled_start' => $this->when->copy()->utc(), 'price_amount' => 100000,
            'address_line' => 'Jl. Uji No. 1', 'landmark' => null, 'contact_phone' => '081234567890',
            'idempotency_key' => Str::random(20)]);
    }
    private function bookingStatus(Booking $b): string {
        return (string) DB::table('bookings')->where('id', $b->id)->value('status');
    }
    private function assignmentCount(Booking $b): int {
        return DB::table('assignments')->where('booking_id', $b->id)->count();
    }
    private function candidateIds(Booking $b): array {
        return array_map('intval', array_column(app(PartnerMatcher::class)->candidates($b), 'partner_id'));
    }
    public function test_assign_eligible_partner_without_override(): void {
        $p = $this->partner();
        $b = $this->waiting();
        $this->actingAs($this->user(UserRole::OpsAdmin), 'sanctum')->postJson("/api/v1/admin/bookings/{$b->id}/assign", ['partner_id' => $p->id])
            ->assertOk()->assertJsonPath('data.status', 'OFFERED');
        $this->assertSame(1, $this->assignmentCount($b));
    }
    public function test_assign_ineligible_partner_is_rejected(): void {
        $p = $this->partner(false);
        $b = $this->waiting();
        $this->actingAs($this->user(UserRole::OpsAdmin), 'sanctum')->postJson("/api/v1/admin/bookings/{$b->id}/assign", ['partner_id' => $p->id])
            ->assertStatus(422)->assertJsonValidationErrors('partner_id');
        $this->assertSame('WAITING_MIDWIFE', $this->bookingStatus($b));
        $this->assertSame(0, $this->assignmentCount($b));
    }
    public function test_override_with_reason_is_recorded(): void {
        $p = $this->partner(false);
        $b = $this->waiting();
        $this->actingAs($this->user(UserRole::OpsAdmin), 'sanctum')->postJson("/api/v1/admin/bookings/{$b->id}/assign",
            ['partner_id' => $p->id, 'override' => true, 'override_reason' => 'Mitra setuju lewat telepon'])
            ->assertOk()->assertJsonPath('data.status', 'OFFERED');
        $this->assertDatabaseHas('audit_events', ['action' => 'booking.assign_override', 'entity_id' => $b->id]);
        $reason = (string) DB::table('booking_status_histories')->where('booking_id', $b->id)->where('to_status', 'OFFERED')->value('reason');
        $this->assertStringStartsWith('Override: ', $reason);
    }
    public function test_override_requires_reason(): void {
        $p = $this->partner(false);
        $b = $this->waiting();
        $this->actingAs($this->user(UserRole::OpsAdmin), 'sanctum')->postJson("/api/v1/admin/bookings/{$b->id}/assign",
            ['partner_id' => $p->id, 'override' => true])
            ->assertStatus(422)->assertJsonValidationErrors('override_reason');
        $this->assertSame(0, $this->assignmentCount($b));
    }
    public function test_sip_valid_through_visit_date(): void {
        $p = $this->partner(true, $this->when->toDateString());
        $b = $this->waiting();
        $this->assertSame([$p->id], $this->candidateIds($b));
    }
    public function test_sip_expiring_before_visit_date_is_excluded(): void {
        $this->partner(true, $this->when->copy()->subDay()->toDateString());
        $b = $this->waiting();
        $this->assertSame([], $this->candidateIds($b));
    }
    public function test_partner_sees_offer_without_contact_details(): void {
        $p = $this->partner();
        $b = $this->waiting();
        $this->actingAs($this->user(UserRole::OpsAdmin), 'sanctum')->postJson("/api/v1/admin/bookings/{$b->id}/assign", ['partner_id' => $p->id])->assertOk();
        $this->actingAs($p, 'sanctum')->getJson('/api/v1/bookings')->assertOk()
            ->assertJsonPath('data.0.status', 'OFFERED')->assertJsonMissingPath('data.0.address_line');
    }
}
