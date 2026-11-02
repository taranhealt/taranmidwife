<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class FavoriteTest extends TestCase {
    use RefreshDatabase;
    private function user(UserRole $role): User { $u = User::factory()->create(); $u->forceFill(['role' => $role, 'status' => 'active'])->save(); return $u; }
    private function service(bool $active = true): Service { return Service::create(['code' => 'FAV-' . \Illuminate\Support\Str::random(8), 'name' => 'Layanan Favorit', 'price_amount' => 50000, 'active' => $active]); }
    public function test_patient_can_add_list_and_remove_favorite_idempotently(): void {
        $patient = $this->user(UserRole::Patient); $service = $this->service();
        $this->actingAs($patient, 'sanctum')->postJson('/api/v1/favorites', ['service_id' => $service->id])->assertCreated()->assertJsonPath('data.is_favorite', true);
        $this->actingAs($patient, 'sanctum')->postJson('/api/v1/favorites', ['service_id' => $service->id])->assertOk();
        $this->actingAs($patient, 'sanctum')->getJson('/api/v1/favorites')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $service->id);
        $this->actingAs($patient, 'sanctum')->deleteJson('/api/v1/favorites/' . $service->id)->assertOk()->assertJsonPath('data.is_favorite', false);
        $this->actingAs($patient, 'sanctum')->getJson('/api/v1/favorites')->assertOk()->assertJsonCount(0, 'data');
    }
    public function test_favorites_are_patient_scoped_and_reject_non_patients_or_inactive_services(): void {
        $patient = $this->user(UserRole::Patient); $other = $this->user(UserRole::Patient); $service = $this->service();
        $this->actingAs($patient, 'sanctum')->postJson('/api/v1/favorites', ['service_id' => $service->id])->assertCreated();
        $this->actingAs($other, 'sanctum')->getJson('/api/v1/favorites')->assertOk()->assertJsonCount(0, 'data');
        $inactive = $this->service(false);
        $this->actingAs($patient, 'sanctum')->postJson('/api/v1/favorites', ['service_id' => $inactive->id])->assertNotFound();
        $this->actingAs($this->user(UserRole::Partner), 'sanctum')->getJson('/api/v1/favorites')->assertStatus(403);
    }
}
