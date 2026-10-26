<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Booking;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class BookingFlowTest extends TestCase {
    use RefreshDatabase;
    private Service $service; private Area $area;
    protected function setUp(): void {
        parent::setUp();
        $this->service = Service::create(['code' => 'UJI-1', 'name' => 'Layanan Uji', 'price_amount' => 100000, 'active' => true]);
        $this->area = Area::create(['name' => 'Wilayah Uji', 'active' => true]);
    }
    private function user(UserRole $role, string $status = 'active'): User {
        $u = User::factory()->create();
        $u->forceFill(['role' => $role, 'status' => $status])->save();
        return $u;
    }
    private function partner(string $verification = 'ACTIVE'): User {
        $u = $this->user(UserRole::Partner);
        PartnerProfile::create(['user_id' => $u->id, 'verification_status' => $verification]);
        return $u;
    }
    private function slot(int $days = 2, int $hour = 10): string {
        return now('Asia/Jakarta')->addDays($days)->setTime($hour, 0, 0)->toIso8601String();
    }
    private function body(array $o = []): array {
        return array_merge(['service_id' => $this->service->id, 'area_id' => $this->area->id,
            'scheduled_start' => $this->slot(), 'address_line' => 'Jl. Uji No. 1 (data sintetis)',
            'contact_phone' => '081200000001'], $o);
    }
    private function create(User $patient, array $o = [], string $key = 'kunci-uji-0001'): \Illuminate\Testing\TestResponse {
        return $this->actingAs($patient, 'sanctum')->postJson('/api/v1/bookings', $this->body($o), ['Idempotency-Key' => $key]);
    }
    public function test_create_booking_snapshots_price_and_is_idempotent(): void {
        $p = $this->user(UserRole::Patient);
        $a = $this->create($p)->assertCreated()->assertJsonPath('data.status', 'REQUESTED')->assertJsonPath('data.price_amount', 100000);
        $this->service->update(['price_amount' => 999999]);
        $b = $this->create($p)->assertOk();
        $this->assertSame($a->json('data.id'), $b->json('data.id'));
        $this->assertSame(100000, $b->json('data.price_amount'));
        $this->assertDatabaseCount('bookings', 1);
    }
    public function test_idempotency_key_required_and_slot_rules(): void {
        $p = $this->user(UserRole::Patient);
        $this->actingAs($p, 'sanctum')->postJson('/api/v1/bookings', $this->body())->assertStatus(422);
        $this->create($p, ['scheduled_start' => $this->slot(2, 11)], 'kunci-uji-0002')->assertStatus(422)->assertJsonValidationErrors('scheduled_start');
        $this->create($p, ['scheduled_start' => now('Asia/Jakarta')->subDay()->setTime(10, 0)->toIso8601String()], 'kunci-uji-0003')->assertStatus(422);
        $this->assertDatabaseCount('bookings', 0);
    }
    public function test_inactive_service_or_area_rejected(): void {
        $p = $this->user(UserRole::Patient);
        $this->service->update(['active' => false]);
        $this->create($p)->assertStatus(422)->assertJsonValidationErrors('service_id');
        $this->service->update(['active' => true]); $this->area->update(['active' => false]);
        $this->create($p, [], 'kunci-uji-0004')->assertStatus(422)->assertJsonValidationErrors('area_id');
    }
    public function test_cross_account_access_is_blocked(): void {
        $a = $this->user(UserRole::Patient); $b = $this->user(UserRole::Patient);
        $id = $this->create($a)->json('data.id');
        $this->actingAs($b, 'sanctum')->getJson("/api/v1/bookings/{$id}")->assertStatus(404);
        $this->actingAs($b, 'sanctum')->postJson("/api/v1/bookings/{$id}/cancel")->assertStatus(404);
        $this->actingAs($b, 'sanctum')->getJson('/api/v1/bookings')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($a, 'sanctum')->getJson("/api/v1/bookings/{$id}")->assertOk();
    }
    public function test_role_boundaries(): void {
        $patient = $this->user(UserRole::Patient);
        $id = $this->create($patient)->json('data.id');
        $this->actingAs($patient, 'sanctum')->postJson("/api/v1/admin/bookings/{$id}/screen")->assertStatus(403);
        $this->actingAs($this->partner(), 'sanctum')->postJson('/api/v1/bookings', $this->body(), ['Idempotency-Key' => 'kunci-uji-0009'])->assertStatus(403);
        $this->actingAs($this->user(UserRole::Reviewer), 'sanctum')->getJson('/api/v1/bookings')->assertStatus(403);
        $this->actingAs($this->user(UserRole::OpsAdmin), 'sanctum')->postJson("/api/v1/midwife/bookings/{$id}/accept")->assertStatus(403);
    }
    public function test_full_flow_and_contact_visibility(): void {
        $patient = $this->user(UserRole::Patient); $ops = $this->user(UserRole::OpsAdmin); $partner = $this->partner();
        $id = $this->create($patient)->json('data.id');
        $this->actingAs($ops, 'sanctum')->postJson("/api/v1/admin/bookings/{$id}/screen")->assertOk()->assertJsonPath('data.status', 'WAITING_MIDWIFE');
        $this->actingAs($ops, 'sanctum')->postJson("/api/v1/admin/bookings/{$id}/assign", ['partner_id' => $partner->id])->assertOk()->assertJsonPath('data.status', 'OFFERED');
        $this->actingAs($partner, 'sanctum')->getJson("/api/v1/bookings/{$id}")->assertOk()->assertJsonMissingPath('data.address_line');
        $this->actingAs($partner, 'sanctum')->postJson("/api/v1/midwife/bookings/{$id}/accept")->assertOk()->assertJsonPath('data.status', 'CONFIRMED')->assertJsonPath('data.address_line', 'Jl. Uji No. 1 (data sintetis)');
        $this->actingAs($partner, 'sanctum')->postJson("/api/v1/midwife/bookings/{$id}/start")->assertOk()->assertJsonPath('data.status', 'IN_PROGRESS');
        $this->actingAs($partner, 'sanctum')->postJson("/api/v1/midwife/bookings/{$id}/complete")->assertOk()->assertJsonPath('data.status', 'COMPLETED');
        $this->assertDatabaseCount('booking_status_histories', 6);
        $this->assertDatabaseHas('audit_events', ['action' => 'booking.completed']);
    }
    public function test_invalid_transitions_rejected_by_backend(): void {
        $patient = $this->user(UserRole::Patient); $ops = $this->user(UserRole::OpsAdmin); $partner = $this->partner();
        $id = $this->create($patient)->json('data.id');
        $this->actingAs($ops, 'sanctum')->postJson("/api/v1/admin/bookings/{$id}/assign", ['partner_id' => $partner->id])
            ->assertStatus(409)->assertJsonPath('code', 'INVALID_TRANSITION');
        $this->assertSame('REQUESTED', Booking::find($id)->status);
        $this->assertDatabaseCount('assignments', 0);
    }
    public function test_double_booking_blocked_and_cancel_frees_slot(): void {
        $ops = $this->user(UserRole::OpsAdmin); $partner = $this->partner();
        $p1 = $this->user(UserRole::Patient); $p2 = $this->user(UserRole::Patient);
        $id1 = $this->create($p1)->json('data.id'); $id2 = $this->create($p2)->json('data.id');
        foreach ([$id1, $id2] as $id) { $this->actingAs($ops, 'sanctum')->postJson("/api/v1/admin/bookings/{$id}/screen")->assertOk(); }
        $this->actingAs($ops, 'sanctum')->postJson("/api/v1/admin/bookings/{$id1}/assign", ['partner_id' => $partner->id])->assertOk();
        $this->actingAs($ops, 'sanctum')->postJson("/api/v1/admin/bookings/{$id2}/assign", ['partner_id' => $partner->id])
            ->assertStatus(409)->assertJsonPath('code', 'SLOT_UNAVAILABLE');
        $this->assertSame('WAITING_MIDWIFE', Booking::find($id2)->status);
        $this->actingAs($p1, 'sanctum')->postJson("/api/v1/bookings/{$id1}/cancel")->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->actingAs($ops, 'sanctum')->postJson("/api/v1/admin/bookings/{$id2}/assign", ['partner_id' => $partner->id])->assertOk();
    }
    public function test_decline_returns_to_waiting_and_frees_slot(): void {
        $ops = $this->user(UserRole::OpsAdmin); $partner = $this->partner();
        $id = $this->create($this->user(UserRole::Patient))->json('data.id');
        $this->actingAs($ops, 'sanctum')->postJson("/api/v1/admin/bookings/{$id}/screen")->assertOk();
        $this->actingAs($ops, 'sanctum')->postJson("/api/v1/admin/bookings/{$id}/assign", ['partner_id' => $partner->id])->assertOk();
        $this->actingAs($partner, 'sanctum')->postJson("/api/v1/midwife/bookings/{$id}/decline")->assertOk()->assertJsonPath('data.status', 'WAITING_MIDWIFE');
        $this->actingAs($ops, 'sanctum')->postJson("/api/v1/admin/bookings/{$id}/assign", ['partner_id' => $partner->id])->assertOk();
    }
    public function test_only_active_partner_can_be_assigned_or_accept(): void {
        $ops = $this->user(UserRole::OpsAdmin);
        $id = $this->create($this->user(UserRole::Patient))->json('data.id');
        $this->actingAs($ops, 'sanctum')->postJson("/api/v1/admin/bookings/{$id}/screen")->assertOk();
        foreach (['APPROVED', 'SUSPENDED', 'DRAFT'] as $st) {
            $this->actingAs($ops, 'sanctum')->postJson("/api/v1/admin/bookings/{$id}/assign", ['partner_id' => $this->partner($st)->id])
                ->assertStatus(422)->assertJsonValidationErrors('partner_id');
        }
        $this->actingAs($ops, 'sanctum')->postJson("/api/v1/admin/bookings/{$id}/assign", ['partner_id' => $this->user(UserRole::Patient)->id])->assertStatus(422);
        $partner = $this->partner();
        $this->actingAs($ops, 'sanctum')->postJson("/api/v1/admin/bookings/{$id}/assign", ['partner_id' => $partner->id])->assertOk();
        $partner->partnerProfile()->update(['verification_status' => 'SUSPENDED']);
        $this->actingAs($partner->fresh(), 'sanctum')->postJson("/api/v1/midwife/bookings/{$id}/accept")->assertStatus(403)->assertJsonPath('code', 'PARTNER_NOT_ACTIVE');
    }
    public function test_other_partner_cannot_act_on_assignment(): void {
        $ops = $this->user(UserRole::OpsAdmin); $partner = $this->partner(); $other = $this->partner();
        $id = $this->create($this->user(UserRole::Patient))->json('data.id');
        $this->actingAs($ops, 'sanctum')->postJson("/api/v1/admin/bookings/{$id}/screen")->assertOk();
        $this->actingAs($ops, 'sanctum')->postJson("/api/v1/admin/bookings/{$id}/assign", ['partner_id' => $partner->id])->assertOk();
        $this->actingAs($other, 'sanctum')->postJson("/api/v1/midwife/bookings/{$id}/accept")->assertStatus(404);
        $this->actingAs($other, 'sanctum')->getJson("/api/v1/bookings/{$id}")->assertStatus(404);
    }
    public function test_address_is_encrypted_at_rest(): void {
        $p = $this->user(UserRole::Patient);
        $this->create($p)->assertCreated();
        $raw = \DB::table('bookings')->value('address_line');
        $this->assertStringNotContainsString('Jl. Uji', $raw);
    }
    public function test_public_catalog_lists_only_active(): void {
        Service::create(['code' => 'UJI-2', 'name' => 'Tidak Aktif', 'price_amount' => 1, 'active' => false]);
        $this->getJson('/api/v1/services')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/areas')->assertOk()->assertJsonCount(1, 'data');
    }
}
