<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class AuthAccessTest extends TestCase {
    use RefreshDatabase;
    private function payload(array $o = []): array {
        return array_merge(['name' => 'Pasien Uji', 'email' => 'pasien@example.test',
            'password' => 'KataSandiUji123', 'password_confirmation' => 'KataSandiUji123'], $o);
    }
    private function userWithRole(UserRole $role): User {
        $u = User::factory()->create();
        $u->forceFill(['role' => $role])->save();
        return $u;
    }
    public function test_patient_registers_and_reads_own_profile(): void {
        $r = $this->postJson('/api/v1/auth/register', $this->payload());
        $r->assertCreated()->assertJsonPath('user.role', 'patient')->assertJsonMissingPath('user.password');
        $this->withToken($r->json('token'))->getJson('/api/v1/me')
            ->assertOk()->assertJsonPath('user.email', 'pasien@example.test');
    }
    public function test_partner_registration_creates_draft_profile(): void {
        $this->postJson('/api/v1/auth/register', $this->payload(['role' => 'partner', 'email' => 'bidan@example.test']))
            ->assertCreated()->assertJsonPath('user.verification_status', 'DRAFT');
        $this->assertDatabaseHas('partner_profiles', ['verification_status' => 'DRAFT']);
    }
    public function test_cannot_self_assign_privileged_role(): void {
        foreach (['ops_admin', 'reviewer', 'sysadmin', 'admin'] as $role) {
            $this->postJson('/api/v1/auth/register', $this->payload(['role' => $role]))
                ->assertStatus(422)->assertJsonValidationErrors('role');
        }
        $this->assertDatabaseCount('users', 0);
    }
    public function test_login_failure_message_is_identical_for_unknown_and_wrong_password(): void {
        User::factory()->create(['email' => 'ada@example.test']);
        $a = $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.test', 'password' => 'salah-banget']);
        $b = $this->postJson('/api/v1/auth/login', ['email' => 'tidakada@example.test', 'password' => 'salah-banget']);
        $a->assertStatus(401); $b->assertStatus(401);
        $this->assertSame($a->json('message'), $b->json('message'));
    }
    public function test_login_is_rate_limited(): void {
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'x@example.test', 'password' => 'salah'])->assertStatus(401);
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'x@example.test', 'password' => 'salah'])->assertStatus(429);
    }
    public function test_partner_list_access_by_role(): void {
        $this->getJson('/api/v1/admin/partners')->assertStatus(401);
        foreach ([UserRole::Patient, UserRole::Partner, UserRole::OpsAdmin] as $role) {
            $this->actingAs($this->userWithRole($role), 'sanctum')->getJson('/api/v1/admin/partners')->assertStatus(403);
        }
        foreach ([UserRole::Reviewer, UserRole::SysAdmin] as $role) {
            $this->actingAs($this->userWithRole($role), 'sanctum')->getJson('/api/v1/admin/partners')->assertOk();
        }
    }
    public function test_suspended_user_is_blocked_from_privileged_endpoint(): void {
        $u = $this->userWithRole(UserRole::Reviewer);
        $u->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($u, 'sanctum')->getJson('/api/v1/admin/partners')->assertStatus(403);
    }
    public function test_logout_revokes_token(): void {
        $token = $this->postJson('/api/v1/auth/register', $this->payload())->json('token');
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
