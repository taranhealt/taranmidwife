<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class LoginUniformityTest extends TestCase {
    use RefreshDatabase;
    public function test_unknown_email_and_wrong_password_give_identical_responses(): void {
        $u = User::factory()->create();
        $wrong = $this->postJson('/api/v1/auth/login', ['email' => $u->email, 'password' => 'kata-sandi-salah']);
        $unknown = $this->postJson('/api/v1/auth/login', ['email' => 'tidak-ada@example.test', 'password' => 'kata-sandi-salah']);
        $wrong->assertStatus(401);
        $unknown->assertStatus(401);
        $this->assertSame($wrong->json(), $unknown->json());
    }
    public function test_inactive_account_cannot_log_in_even_with_correct_password(): void {
        $u = User::factory()->create(['password' => 'kata-sandi-benar-1']);
        $u->forceFill(['status' => 'suspended'])->save();
        $this->postJson('/api/v1/auth/login', ['email' => $u->email, 'password' => 'kata-sandi-benar-1'])->assertStatus(401);
    }
}
