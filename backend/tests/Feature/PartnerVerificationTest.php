<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\PartnerDocument;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class PartnerVerificationTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); Storage::fake('local'); }
    private function user(UserRole $role): User {
        $u = User::factory()->create();
        $u->forceFill(['role' => $role])->save();
        return $u;
    }
    private function partner(string $status = 'DRAFT'): User {
        $u = $this->user(UserRole::Partner);
        PartnerProfile::create(['user_id' => $u->id, 'verification_status' => $status]);
        return $u;
    }
    private function fill(User $u, array $o = []): void {
        $this->actingAs($u, 'sanctum')->putJson('/api/v1/midwife/profile', array_merge([
            'nik' => '1234567890123456', 'sip_number' => 'SIP-UJI-001', 'sip_issuer' => 'Dinas Uji',
            'sip_expires_on' => now()->addYear()->toDateString(), 'practice_area' => 'Wilayah Uji'], $o))->assertOk();
    }
    private function upload(User $u, string $type, ?UploadedFile $f = null) {
        $f ??= $type === 'FACE_PHOTO' ? UploadedFile::fake()->image('wajah.jpg') : UploadedFile::fake()->create(strtolower($type) . '.pdf', 100, 'application/pdf');
        return $this->actingAs($u, 'sanctum')->post('/api/v1/midwife/documents', ['type' => $type, 'file' => $f], ['Accept' => 'application/json']);
    }
    private function uploadAll(User $u): void { foreach (['SIP', 'STR', 'IDENTITY', 'FACE_PHOTO'] as $t) { $this->upload($u, $t)->assertCreated(); } }
    private function submitted(): User {
        $u = $this->partner(); $this->fill($u); $this->uploadAll($u);
        $this->actingAs($u, 'sanctum')->postJson('/api/v1/midwife/verification/submit')->assertOk()->assertJsonPath('data.verification_status', 'SUBMITTED');
        return $u;
    }
    private function checks(string $result = 'verified'): array {
        $c = [];
        foreach (['IDENTITY', 'PROFESSIONAL_STATUS', 'IHS', 'SIP', 'FACE_PHOTO'] as $t) { $c[$t] = ['result' => $result, 'method' => 'Pemeriksaan manual (uji)']; }
        return ['checks' => $c];
    }
    public function test_nik_is_masked_in_response_and_encrypted_at_rest(): void {
        $u = $this->partner(); $this->fill($u);
        $this->actingAs($u, 'sanctum')->getJson('/api/v1/midwife/profile')->assertOk()->assertJsonPath('data.nik', '************3456');
        $this->assertStringNotContainsString('1234567890123456', (string) DB::table('partner_profiles')->value('nik'));
    }
    public function test_upload_validation_and_replacement(): void {
        $u = $this->partner();
        $this->upload($u, 'SIP', UploadedFile::fake()->create('shell.php', 10, 'application/x-php'))->assertStatus(422);
        $this->upload($u, 'SIP', UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf'))->assertStatus(422);
        $this->upload($u, 'FACE_PHOTO', UploadedFile::fake()->create('wajah.pdf', 10, 'application/pdf'))->assertStatus(422);
        $this->upload($u, 'SIP')->assertCreated(); $this->upload($u, 'SIP')->assertCreated();
        $this->assertSame(1, PartnerDocument::where('type', 'SIP')->count());
        $this->assertCount(1, Storage::disk('local')->allFiles('partner-documents'));
    }
    public function test_submit_requires_complete_profile_and_locks_editing(): void {
        $u = $this->partner();
        $this->actingAs($u, 'sanctum')->postJson('/api/v1/midwife/verification/submit')->assertStatus(422)->assertJsonPath('code', 'INCOMPLETE_PROFILE');
        $this->fill($u); $this->uploadAll($u);
        $this->actingAs($u, 'sanctum')->postJson('/api/v1/midwife/verification/submit')->assertOk();
        $this->actingAs($u, 'sanctum')->putJson('/api/v1/midwife/profile', ['sip_number' => 'BARU'])->assertStatus(409)->assertJsonPath('code', 'PROFILE_LOCKED');
        $this->upload($u, 'SIP')->assertStatus(409);
    }
    public function test_full_verification_path_requires_checks_and_sysadmin(): void {
        $u = $this->submitted(); $pid = $u->partnerProfile->id;
        $reviewer = $this->user(UserRole::Reviewer); $sys = $this->user(UserRole::SysAdmin);
        $this->actingAs($reviewer, 'sanctum')->postJson("/api/v1/admin/partners/{$pid}/start-review")->assertOk()->assertJsonPath('data.verification_status', 'IN_REVIEW');
        $this->actingAs($sys, 'sanctum')->postJson("/api/v1/admin/partners/{$pid}/approve")->assertStatus(422)->assertJsonPath('code', 'CHECKS_INCOMPLETE');
        $this->actingAs($reviewer, 'sanctum')->postJson("/api/v1/admin/partners/{$pid}/checks", $this->checks('unverified_limited'))->assertOk()->assertJsonCount(5, 'data.limitations');
        $this->actingAs($reviewer, 'sanctum')->postJson("/api/v1/admin/partners/{$pid}/approve")->assertStatus(403);
        $this->actingAs($sys, 'sanctum')->postJson("/api/v1/admin/partners/{$pid}/approve")->assertOk()->assertJsonPath('data.verification_status', 'APPROVED');
        $this->actingAs($reviewer, 'sanctum')->postJson("/api/v1/admin/partners/{$pid}/activate")->assertStatus(403);
        $this->actingAs($sys, 'sanctum')->postJson("/api/v1/admin/partners/{$pid}/activate")->assertOk()->assertJsonPath('data.verification_status', 'ACTIVE');
        $this->assertDatabaseHas('partner_verification_events', ['partner_profile_id' => $pid, 'to_status' => 'ACTIVE']);
        $this->actingAs($sys, 'sanctum')->postJson("/api/v1/admin/partners/{$pid}/suspend", [])->assertStatus(422);
        $this->actingAs($sys, 'sanctum')->postJson("/api/v1/admin/partners/{$pid}/suspend", ['reason' => 'Uji'])->assertOk()->assertJsonPath('data.verification_status', 'SUSPENDED');
    }
    public function test_failed_check_blocks_approval(): void {
        $u = $this->submitted(); $pid = $u->partnerProfile->id;
        $this->actingAs($this->user(UserRole::Reviewer), 'sanctum')->postJson("/api/v1/admin/partners/{$pid}/start-review")->assertOk();
        $c = $this->checks(); $c['checks']['SIP']['result'] = 'failed';
        $this->actingAs($this->user(UserRole::Reviewer), 'sanctum')->postJson("/api/v1/admin/partners/{$pid}/checks", $c)->assertOk();
        $this->actingAs($this->user(UserRole::SysAdmin), 'sanctum')->postJson("/api/v1/admin/partners/{$pid}/approve")->assertStatus(422);
    }
    public function test_correction_needs_reason_and_allows_resubmission(): void {
        $u = $this->submitted(); $pid = $u->partnerProfile->id; $rev = $this->user(UserRole::Reviewer);
        $this->actingAs($rev, 'sanctum')->postJson("/api/v1/admin/partners/{$pid}/start-review")->assertOk();
        $this->actingAs($rev, 'sanctum')->postJson("/api/v1/admin/partners/{$pid}/request-correction", [])->assertStatus(422);
        $this->actingAs($rev, 'sanctum')->postJson("/api/v1/admin/partners/{$pid}/request-correction", ['reason' => 'SIP kurang jelas'])->assertOk();
        $this->actingAs($u, 'sanctum')->getJson('/api/v1/midwife/profile')->assertJsonPath('data.review_reason', 'SIP kurang jelas');
        $this->upload($u, 'SIP')->assertCreated();
        $this->actingAs($u, 'sanctum')->postJson('/api/v1/midwife/verification/submit')->assertOk()->assertJsonPath('data.verification_status', 'SUBMITTED');
    }
    public function test_other_roles_cannot_use_admin_partner_endpoints(): void {
        $u = $this->submitted(); $pid = $u->partnerProfile->id;
        foreach ([UserRole::Patient, UserRole::Partner, UserRole::OpsAdmin] as $role) {
            $a = $this->actingAs($this->user($role), 'sanctum');
            $a->getJson("/api/v1/admin/partners/{$pid}")->assertStatus(403);
            $a->postJson("/api/v1/admin/partners/{$pid}/approve")->assertStatus(403);
        }
    }
    public function test_document_download_is_restricted_and_audited(): void {
        $u = $this->submitted(); $p = $u->partnerProfile; $doc = $p->documents()->first();
        $this->actingAs($this->user(UserRole::Patient), 'sanctum')->get("/api/v1/admin/partners/{$p->id}/documents/{$doc->id}")->assertStatus(403);
        $r = $this->actingAs($this->user(UserRole::Reviewer), 'sanctum')->get("/api/v1/admin/partners/{$p->id}/documents/{$doc->id}");
        $r->assertOk();
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertDatabaseHas('audit_events', ['action' => 'partner.document.view', 'entity_id' => $p->id]);
    }
    public function test_document_download_requires_valid_unexpired_signed_url(): void {
        $u = $this->submitted(); $p = $u->partnerProfile; $doc = $p->documents()->first();
        $reviewer = $this->user(UserRole::Reviewer);
        $detail = $this->actingAs($reviewer, 'sanctum')->getJson("/api/v1/admin/partners/{$p->id}")->assertOk();
        $url = $detail->json('data.documents.0.url');
        $this->assertNotEmpty($url);
        $path = parse_url($url, PHP_URL_PATH) . '?' . parse_url($url, PHP_URL_QUERY);
        $this->get($path)->assertOk();
        $expired = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'api.v1.admin.partners.documents.signed', now()->subMinute(),
            ['partner' => $p->id, 'document' => $doc->id]
        );
        $expiredPath = parse_url($expired, PHP_URL_PATH) . '?' . parse_url($expired, PHP_URL_QUERY);
        $this->get($expiredPath)->assertForbidden();
        $tampered = $path . '&tampered=1';
        $this->get($tampered)->assertForbidden();
    }
    public function test_activation_requires_valid_sip_and_expiry_command_works(): void {
        $u = $this->partner('APPROVED');
        $u->partnerProfile->update(['sip_expires_on' => now()->subDay()->toDateString()]);
        $this->actingAs($this->user(UserRole::SysAdmin), 'sanctum')->postJson("/api/v1/admin/partners/{$u->partnerProfile->id}/activate")->assertStatus(422)->assertJsonPath('code', 'SIP_NOT_VALID');
        $x = $this->partner('ACTIVE');
        $x->partnerProfile->update(['sip_expires_on' => now()->subDay()->toDateString()]);
        $this->artisan('partners:expire-sip')->assertSuccessful();
        $this->assertSame('EXPIRED', $x->partnerProfile->fresh()->verification_status->value);
        $this->assertDatabaseHas('partner_verification_events', ['partner_profile_id' => $x->partnerProfile->id, 'to_status' => 'EXPIRED']);
    }
}
