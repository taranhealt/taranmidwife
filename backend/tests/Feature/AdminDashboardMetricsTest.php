<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    public function test_admin_metrics_are_role_protected_and_exclude_personal_data(): void
    {
        $patient = $this->user(UserRole::Patient);
        $partner = $this->user(UserRole::Partner);
        PartnerProfile::create(['user_id' => $partner->id, 'verification_status' => 'ACTIVE']);
        $service = Service::forceCreate([
            'id' => (string) Str::ulid(), 'code' => 'MET-' . Str::random(5), 'name' => 'Layanan Uji',
            'price_amount' => 100000, 'active' => true, 'description' => 'Uji', 'limitations' => 'Uji',
        ]);
        $area = Area::forceCreate(['id' => (string) Str::ulid(), 'name' => 'Area Uji', 'active' => true]);
        $booking = Booking::forceCreate([
            'id' => (string) Str::ulid(), 'code' => 'TM-' . Str::random(8),
            'patient_id' => $patient->id, 'service_id' => $service->id, 'area_id' => $area->id,
            'status' => 'REQUESTED', 'scheduled_start' => now()->addDay(), 'price_amount' => 100000,
            'address_line' => 'Alamat rahasia uji', 'contact_phone' => '081234567890',
            'idempotency_key' => Str::random(20),
        ]);
        Complaint::create([
            'booking_id' => $booking->id, 'reporter_id' => $patient->id,
            'category' => 'OTHER', 'description' => 'Isi pengaduan uji',
        ]);

        $this->actingAs($patient, 'sanctum')->getJson('/api/v1/admin/metrics')->assertForbidden();

        $response = $this->actingAs($this->user(UserRole::OpsAdmin), 'sanctum')
            ->getJson('/api/v1/admin/metrics')
            ->assertOk()
            ->assertJsonPath('data.bookings_total', 1)
            ->assertJsonPath('data.bookings_open', 1)
            ->assertJsonPath('data.active_partners', 1)
            ->assertJsonPath('data.complaints_open', 1);

        $this->assertStringNotContainsString('Alamat rahasia', $response->getContent());
        $this->assertStringNotContainsString('081234567890', $response->getContent());
        $this->assertStringNotContainsString('Isi pengaduan', $response->getContent());
    }
}
