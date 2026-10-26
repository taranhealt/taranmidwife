<?php
namespace Tests\Feature;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DevDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class DevSeederTest extends TestCase {
    use RefreshDatabase;
    public function test_seeder_creates_synthetic_data_and_private_credentials_file(): void {
        Storage::fake('local');
        $this->seed(DevDemoSeeder::class);
        $this->seed(DevDemoSeeder::class); // idempoten
        $this->assertSame(5, User::where('email', 'like', '%.demo@example.test')->count());
        $this->assertSame(3, Service::where('active', true)->count());
        Storage::disk('local')->assertExists('demo-credentials.txt');
        $pw = trim(explode(': ', explode("\n", Storage::disk('local')->get('demo-credentials.txt'))[1])[1]);
        $this->postJson('/api/v1/auth/login', ['email' => 'ops.demo@example.test', 'password' => $pw])->assertOk();
    }
}
