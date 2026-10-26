<?php
namespace Tests\Feature;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
class PasswordResetTest extends TestCase {
    use RefreshDatabase;
    private const NEWPASS = 'SandiBaru-12345';
    private function makeUser(string $status = 'active'): User {
        $u = User::factory()->create();
        $u->forceFill(['status' => $status])->save();
        return $u;
    }
    private function requestToken(User $u): string {
        Mail::fake();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $u->email])->assertOk();
        $url = null;
        Mail::assertSent(ResetPasswordMail::class, function ($m) use (&$url, $u) {
            $url = $m->url;
            return $m->hasTo($u->email);
        });
        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $q);
        $this->assertArrayHasKey('token', $q);
        return $q['token'];
    }
    private function reset(User $u, string $token, array $o = []) {
        return $this->postJson('/api/v1/auth/reset-password', array_merge(['email' => $u->email, 'token' => $token,
            'password' => self::NEWPASS, 'password_confirmation' => self::NEWPASS], $o));
    }
    public function test_active_user_gets_link_and_generic_response(): void {
        $u = $this->makeUser();
        Mail::fake();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $u->email])
            ->assertOk()->assertJsonPath('message', \App\Http\Controllers\Api\PasswordResetController::GENERIC);
        Mail::assertSent(ResetPasswordMail::class, 1);
        $this->assertDatabaseHas('audit_events', ['action' => 'auth.password_reset_request']);
    }
    public function test_unknown_and_inactive_accounts_get_same_response_without_mail(): void {
        Mail::fake();
        $deleted = $this->makeUser('deleted');
        $suspended = $this->makeUser('suspended');
        $a = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'tidak-ada@example.test'])->assertOk()->json();
        $b = $this->postJson('/api/v1/auth/forgot-password', ['email' => $deleted->email])->assertOk()->json();
        $c = $this->postJson('/api/v1/auth/forgot-password', ['email' => $suspended->email])->assertOk()->json();
        $this->assertSame($a, $b);
        $this->assertSame($a, $c);
        Mail::assertNothingSent();
    }
    public function test_reset_changes_password_and_revokes_tokens(): void {
        $u = $this->makeUser();
        $u->createToken('lama');
        $token = $this->requestToken($u);
        $this->reset($u, $token)->assertOk();
        $this->assertTrue(Hash::check(self::NEWPASS, $u->fresh()->password));
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $u->id)->count());
        $this->postJson('/api/v1/auth/login', ['email' => $u->email, 'password' => self::NEWPASS])->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => $u->email, 'password' => 'password'])->assertStatus(401);
        $this->assertDatabaseHas('audit_events', ['action' => 'auth.password_reset']);
    }
    public function test_token_cannot_be_reused(): void {
        $u = $this->makeUser();
        $token = $this->requestToken($u);
        $this->reset($u, $token)->assertOk();
        $this->reset($u, $token, ['password' => 'SandiLain-67890', 'password_confirmation' => 'SandiLain-67890'])
            ->assertStatus(422)->assertJsonPath('code', 'INVALID_RESET_TOKEN');
        $this->assertTrue(Hash::check(self::NEWPASS, $u->fresh()->password));
    }
    public function test_invalid_token_and_weak_input_are_rejected(): void {
        $u = $this->makeUser();
        $token = $this->requestToken($u);
        $this->reset($u, 'token-salah')->assertStatus(422)->assertJsonPath('code', 'INVALID_RESET_TOKEN');
        $this->reset($u, $token, ['password_confirmation' => 'beda-sekali-12345'])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->reset($u, $token, ['password' => 'pendek', 'password_confirmation' => 'pendek'])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->assertTrue(Hash::check('password', $u->fresh()->password));
    }
    public function test_reset_is_blocked_after_account_becomes_inactive(): void {
        $u = $this->makeUser();
        $token = $this->requestToken($u);
        $u->forceFill(['status' => 'suspended'])->save();
        $this->reset($u, $token)->assertStatus(422)->assertJsonPath('code', 'INVALID_RESET_TOKEN');
        $this->assertTrue(Hash::check('password', $u->fresh()->password));
    }
}
