<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
class ComplaintTest extends TestCase {
    use RefreshDatabase;
    private function user(UserRole $role): User {
        $u = User::factory()->create();
        $u->forceFill(['role' => $role])->save();
        return $u;
    }
    private function booking(User $patient, string $status = 'COMPLETED'): Booking {
        $svc = Service::forceCreate(['id' => (string) Str::ulid(), 'code' => 'T-' . Str::random(6), 'name' => 'Layanan Uji',
            'price_amount' => 100000, 'active' => true, 'description' => '[DATA UJI]', 'limitations' => 'Uji']);
        $area = Area::forceCreate(['id' => (string) Str::ulid(), 'name' => 'Area ' . Str::random(5), 'active' => true]);
        return Booking::forceCreate(['id' => (string) Str::ulid(), 'code' => 'TM-' . strtoupper(Str::random(8)),
            'patient_id' => $patient->id, 'service_id' => $svc->id, 'area_id' => $area->id, 'status' => $status,
            'scheduled_start' => now()->addDays(2), 'price_amount' => 100000, 'address_line' => 'Jl. Uji No. 1',
            'landmark' => null, 'contact_phone' => '081234567890', 'idempotency_key' => Str::random(20)]);
    }
    private function assign(Booking $b, User $partner): void {
        DB::table('assignments')->insert(['id' => (string) Str::ulid(), 'booking_id' => $b->id, 'partner_id' => $partner->id,
            'scheduled_start' => $b->scheduled_start, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
    private function file(User $u, Booking $b, array $o = []) {
        return $this->actingAs($u, 'sanctum')->postJson('/api/v1/complaints', array_merge([
            'booking_id' => $b->id, 'category' => 'SERVICE_QUALITY',
            'description' => 'Deskripsi pengaduan uji yang cukup panjang.'], $o));
    }
    public function test_authentication_roles_and_validation(): void {
        $this->postJson('/api/v1/complaints', [])->assertStatus(401);
        $this->actingAs($this->user(UserRole::OpsAdmin), 'sanctum')->postJson('/api/v1/complaints', [])->assertStatus(403);
        $p = $this->user(UserRole::Patient); $b = $this->booking($p);
        $this->file($p, $b, ['description' => 'pendek'])->assertStatus(422);
        $this->file($p, $b, ['category' => 'TIDAK_ADA'])->assertStatus(422);
        $this->file($p, $b, ['booking_id' => 'abc'])->assertStatus(422);
        $this->assertSame(0, Complaint::count());
    }
    public function test_reporter_can_file_and_text_is_encrypted_at_rest(): void {
        $p = $this->user(UserRole::Patient); $b = $this->booking($p);
        $r = $this->file($p, $b)->assertCreated()->assertJsonPath('data.status', 'OPEN')
            ->assertJsonPath('data.booking_code', $b->code);
        $r->assertJsonMissingPath('data.safety_flag');
        $this->assertStringNotContainsString('Deskripsi pengaduan uji', (string) DB::table('complaints')->value('description'));
        $this->assertSame(1, DB::table('complaint_events')->where('to_status', 'OPEN')->count());
    }
    public function test_only_related_bookings_can_be_reported(): void {
        $p1 = $this->user(UserRole::Patient); $p2 = $this->user(UserRole::Patient); $b = $this->booking($p1);
        $this->file($p2, $b)->assertStatus(404);
        $partner = $this->user(UserRole::Partner);
        $this->file($partner, $b)->assertStatus(404);
        $this->assign($b, $partner);
        $this->file($partner, $b)->assertCreated();
        $this->file($p1, $b)->assertCreated();
        $this->assertSame(2, Complaint::count());
    }
    public function test_duplicate_open_complaint_is_rejected(): void {
        $p = $this->user(UserRole::Patient); $b = $this->booking($p);
        $this->file($p, $b)->assertCreated();
        $this->file($p, $b)->assertStatus(409)->assertJsonPath('code', 'DUPLICATE_COMPLAINT');
        $this->file($p, $b, ['category' => 'SCHEDULING'])->assertCreated();
        Complaint::where('category', 'SERVICE_QUALITY')->update(['status' => 'CLOSED']);
        $this->file($p, $b)->assertCreated();
        $this->assertSame(3, Complaint::count());
    }
    public function test_safety_category_is_flagged_but_not_decided(): void {
        $p = $this->user(UserRole::Patient); $b = $this->booking($p);
        $r = $this->file($p, $b, ['category' => 'SAFETY'])->assertCreated()->assertJsonStructure(['data', 'notice']);
        $r->assertJsonMissingPath('data.safety_flag');
        $this->file($p, $b)->assertCreated();
        $this->actingAs($this->user(UserRole::OpsAdmin), 'sanctum');
        $this->getJson('/api/v1/admin/complaints?safety=1')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.safety_flag', true)->assertJsonPath('data.0.status', 'OPEN');
        $this->getJson('/api/v1/admin/complaints')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/admin/complaints?status=BUKAN')->assertStatus(422);
    }
    public function test_reporters_only_see_their_own_complaints(): void {
        $p1 = $this->user(UserRole::Patient); $p2 = $this->user(UserRole::Patient);
        $b1 = $this->booking($p1); $b2 = $this->booking($p2);
        $id1 = $this->file($p1, $b1)->assertCreated()->json('data.id');
        $this->file($p2, $b2)->assertCreated();
        $this->actingAs($p1, 'sanctum')->getJson('/api/v1/complaints')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/complaints/{$id1}")->assertOk()->assertJsonPath('data.booking_code', $b1->code)
            ->assertJsonMissingPath('data.safety_flag');
        $this->actingAs($p2, 'sanctum')->getJson("/api/v1/complaints/{$id1}")->assertStatus(404);
    }
    public function test_handling_workflow_and_events(): void {
        $p = $this->user(UserRole::Patient); $b = $this->booking($p);
        $id = $this->file($p, $b)->assertCreated()->json('data.id');
        $ops = $this->user(UserRole::OpsAdmin);
        $this->actingAs($ops, 'sanctum');
        $this->postJson("/api/v1/admin/complaints/{$id}/resolve", [])->assertStatus(422);
        $this->postJson("/api/v1/admin/complaints/{$id}/resolve", ['note' => 'Catatan uji'])->assertStatus(409)->assertJsonPath('code', 'INVALID_TRANSITION');
        $this->postJson("/api/v1/admin/complaints/{$id}/start-review")->assertOk()->assertJsonPath('data.status', 'IN_REVIEW');
        $this->actingAs($p, 'sanctum')->getJson("/api/v1/complaints/{$id}")->assertJsonPath('data.resolution_note', null);
        $this->actingAs($ops, 'sanctum')->postJson("/api/v1/admin/complaints/{$id}/resolve", ['note' => 'Sudah ditindaklanjuti (uji)'])
            ->assertOk()->assertJsonPath('data.status', 'RESOLVED');
        $this->actingAs($p, 'sanctum')->getJson("/api/v1/complaints/{$id}")->assertJsonPath('data.resolution_note', 'Sudah ditindaklanjuti (uji)');
        $this->actingAs($ops, 'sanctum')->postJson("/api/v1/admin/complaints/{$id}/reopen")->assertOk()->assertJsonPath('data.status', 'IN_REVIEW');
        $this->postJson("/api/v1/admin/complaints/{$id}/resolve", ['note' => 'Selesai (uji)'])->assertOk();
        $this->postJson("/api/v1/admin/complaints/{$id}/close")->assertOk()->assertJsonPath('data.status', 'CLOSED');
        $this->postJson("/api/v1/admin/complaints/{$id}/start-review")->assertStatus(409);
        $this->postJson("/api/v1/admin/complaints/{$id}/reopen")->assertStatus(409);
        $this->assertSame(6, DB::table('complaint_events')->where('complaint_id', $id)->count());
    }
    public function test_admin_permissions(): void {
        $p = $this->user(UserRole::Patient); $b = $this->booking($p);
        $id = $this->file($p, $b)->assertCreated()->json('data.id');
        foreach ([UserRole::Patient, UserRole::Partner] as $role) {
            $a = $this->actingAs($this->user($role), 'sanctum');
            $a->getJson('/api/v1/admin/complaints')->assertStatus(403);
            $a->postJson("/api/v1/admin/complaints/{$id}/start-review")->assertStatus(403);
        }
        $sys = $this->actingAs($this->user(UserRole::SysAdmin), 'sanctum');
        $sys->getJson('/api/v1/admin/complaints')->assertOk();
        $sys->getJson("/api/v1/admin/complaints/{$id}")->assertOk();
        $sys->postJson("/api/v1/admin/complaints/{$id}/start-review")->assertStatus(403);
        $this->actingAs($this->user(UserRole::Reviewer), 'sanctum')->postJson("/api/v1/admin/complaints/{$id}/start-review")->assertOk();
    }
    public function test_audit_events_are_recorded(): void {
        $p = $this->user(UserRole::Patient); $b = $this->booking($p);
        $id = $this->file($p, $b)->assertCreated()->json('data.id');
        $ops = $this->user(UserRole::OpsAdmin);
        $this->actingAs($ops, 'sanctum')->getJson("/api/v1/admin/complaints/{$id}")->assertOk();
        $this->postJson("/api/v1/admin/complaints/{$id}/start-review")->assertOk();
        foreach (['complaint.create', 'complaint.view', 'complaint.in_review'] as $action) {
            $this->assertDatabaseHas('audit_events', ['action' => $action, 'entity_id' => $id]);
        }
    }
}
