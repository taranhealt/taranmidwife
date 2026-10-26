<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Booking;
use App\Models\PartnerDocument;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
class AccountDeletionTest extends TestCase {
    use RefreshDatabase;
    private const PW = 'KataSandiUji123';
    protected function setUp(): void { parent::setUp(); Storage::fake('local'); }
    private function user(UserRole $role): User {
        $u = User::factory()->create();
        $u->forceFill(['role' => $role, 'password' => self::PW])->save();
        return $u;
    }
    private function booking(User $patient, string $status): Booking {
        $svc = Service::forceCreate(['id' => (string) Str::ulid(), 'code' => 'T-' . Str::random(6), 'name' => 'Layanan Uji',
            'price_amount' => 100000, 'active' => true, 'description' => '[DATA UJI]', 'limitations' => 'Uji']);
        $area = Area::forceCreate(['id' => (string) Str::ulid(), 'name' => 'Area ' . Str::random(5), 'active' => true]);
        return Booking::forceCreate(['id' => (string) Str::ulid(), 'code' => 'TM-' . strtoupper(Str::random(8)),
            'patient_id' => $patient->id, 'service_id' => $svc->id, 'area_id' => $area->id, 'status' => $status,
            'scheduled_start' => now()->addDays(2), 'price_amount' => 100000, 'address_line' => 'Jl. Uji Rahasia No. 1',
            'landmark' => 'Dekat uji', 'contact_phone' => '081234567890', 'idempotency_key' => Str::random(20)]);
    }
    private function assign(Booking $b, User $partner): void {
        DB::table('assignments')->insert(['id' => (string) Str::ulid(), 'booking_id' => $b->id, 'partner_id' => $partner->id,
            'scheduled_start' => $b->scheduled_start, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
    private function del(User $u, ?string $pw = self::PW) {
        return $this->actingAs($u, 'sanctum')->deleteJson('/api/v1/me', $pw === null ? [] : ['password' => $pw]);
    }
    public function test_requires_authentication_and_password(): void {
        $this->deleteJson('/api/v1/me', ['password' => self::PW])->assertStatus(401);
        $u = $this->user(UserRole::Patient);
        $this->del($u, null)->assertStatus(422);
        $this->del($u, 'salah-total')->assertStatus(403)->assertJsonPath('code', 'INVALID_PASSWORD');
        $this->assertSame('active', $u->fresh()->status);
    }
    public function test_patient_is_anonymized_but_history_is_kept(): void {
        $u = $this->user(UserRole::Patient);
        $u->forceFill(['phone' => '081200000001'])->save();
        $email = $u->email;
        $b = $this->booking($u, 'COMPLETED');
        $u->createToken('mobile');
        $this->del($u)->assertOk();
        $f = $u->fresh();
        $this->assertSame('deleted', $f->status);
        $this->assertSame("deleted-{$u->id}@deleted.invalid", $f->email);
        $this->assertSame('Pengguna Dihapus', $f->name);
        $this->assertNull($f->phone);
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $u->id)->count());
        $r = Booking::find($b->id);
        $this->assertNotNull($r);
        $this->assertSame('[DIHAPUS]', $r->address_line);
        $this->assertSame('[DIHAPUS]', $r->contact_phone);
        $this->assertNull($r->landmark);
        $this->assertSame($u->id, (int) $r->patient_id);
        $this->assertDatabaseHas('audit_events', ['action' => 'account.delete', 'outcome' => 'success', 'entity_id' => (string) $u->id]);
        $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => self::PW])->assertStatus(401);
    }
    public function test_active_booking_blocks_patient_deletion_and_changes_nothing(): void {
        foreach (['REQUESTED', 'CONFIRMED'] as $status) {
            $u = $this->user(UserRole::Patient);
            $b = $this->booking($u, $status);
            $this->del($u)->assertStatus(409)->assertJsonPath('code', 'ACTIVE_BOOKINGS');
            $this->assertSame('active', $u->fresh()->status);
            $this->assertSame('Jl. Uji Rahasia No. 1', Booking::find($b->id)->address_line);
        }
    }
    public function test_cancelled_booking_does_not_block(): void {
        $u = $this->user(UserRole::Patient);
        $this->booking($u, 'CANCELLED');
        $this->del($u)->assertOk();
    }
    public function test_partner_with_active_assignment_is_blocked_then_allowed_after_completion(): void {
        $partner = $this->user(UserRole::Partner);
        PartnerProfile::create(['user_id' => $partner->id, 'verification_status' => 'ACTIVE']);
        $patient = $this->user(UserRole::Patient);
        $b = $this->booking($patient, 'CONFIRMED');
        $this->assign($b, $partner);
        $this->del($partner)->assertStatus(409)->assertJsonPath('code', 'ACTIVE_BOOKINGS');
        $this->assertSame('active', $partner->fresh()->status);
        $b->forceFill(['status' => 'COMPLETED'])->save();
        $this->del($partner)->assertOk();
        $this->assertSame('deleted', $partner->fresh()->status);
    }
    public function test_partner_data_and_documents_are_erased(): void {
        $partner = $this->user(UserRole::Partner);
        $p = PartnerProfile::create(['user_id' => $partner->id, 'verification_status' => 'ACTIVE']);
        $p->forceFill(['nik' => '1234567890123456', 'sip_number' => 'SIP-UJI', 'sip_issuer' => 'Dinas Uji',
            'sip_expires_on' => now()->addYear()->toDateString(), 'practice_area' => 'Wilayah Uji'])->save();
        $key = "partner-documents/{$p->id}/berkas.pdf";
        Storage::disk('local')->put($key, 'isi uji');
        PartnerDocument::create(['partner_profile_id' => $p->id, 'type' => 'SIP', 'storage_key' => $key,
            'mime' => 'application/pdf', 'size_bytes' => 7, 'sha256' => hash('sha256', 'isi uji')]);
        $this->del($partner)->assertOk();
        $f = $p->fresh();
        $this->assertNull($f->nik);
        $this->assertNull($f->sip_number);
        $this->assertSame('SUSPENDED', $f->verification_status->value);
        $this->assertSame(0, PartnerDocument::where('partner_profile_id', $p->id)->count());
        Storage::disk('local')->assertMissing($key);
        $this->assertDatabaseHas('partner_verification_events', ['partner_profile_id' => $p->id, 'to_status' => 'SUSPENDED']);
    }
    public function test_staff_accounts_cannot_self_delete(): void {
        foreach ([UserRole::OpsAdmin, UserRole::Reviewer, UserRole::SysAdmin] as $role) {
            $u = $this->user($role);
            $this->del($u)->assertStatus(403)->assertJsonPath('code', 'STAFF_ACCOUNT');
            $this->assertSame('active', $u->fresh()->status);
        }
    }
}
