<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class BookingPaymentTest extends TestCase {
    use RefreshDatabase;
    private function user(UserRole $role): User {
        $u = User::factory()->create(); $u->forceFill(['role' => $role, 'status' => 'active'])->save(); return $u;
    }
    private function completedBooking(User $patient): Booking {
        $service = Service::create(['code' => 'PAY-1', 'name' => 'Layanan Uji', 'price_amount' => 125000, 'active' => true]);
        $area = Area::create(['name' => 'Area Uji', 'active' => true]);
        $b = Booking::create(['code' => 'PAY-' . strtoupper(substr((string) \Illuminate\Support\Str::ulid(), -8)),
            'patient_id' => $patient->id, 'service_id' => $service->id, 'area_id' => $area->id,
            'scheduled_start' => now()->addDay(), 'price_amount' => 125000, 'address_line' => 'Alamat sintetis',
            'contact_phone' => '081200000001', 'idempotency_key' => 'payment-' . \Illuminate\Support\Str::random(12)]);
        $b->forceFill(['status' => 'COMPLETED'])->save();
        return $b;
    }
    public function test_ops_admin_can_record_manual_payment_once_and_patient_can_view_history(): void {
        $patient = $this->user(UserRole::Patient); $ops = $this->user(UserRole::OpsAdmin);
        $b = $this->completedBooking($patient);
        $this->actingAs($ops, 'sanctum')->postJson("/api/v1/admin/bookings/{$b->id}/payment", [
            'status' => 'PAID', 'method' => 'BANK_TRANSFER', 'note' => 'Bukti diverifikasi secara manual',
        ])->assertOk()->assertJsonPath('data.status', 'PAID')->assertJsonPath('data.amount', 125000);
        $this->actingAs($patient, 'sanctum')->getJson("/api/v1/bookings/{$b->id}/payment")
            ->assertOk()->assertJsonPath('data.status', 'PAID')->assertJsonPath('data.amount', 125000)
            ->assertJsonPath('data.history.0.to_status', 'PAID')->assertJsonPath('data.history.0.method', 'BANK_TRANSFER');
        $this->assertDatabaseHas('audit_events', ['action' => 'booking.payment.paid', 'entity_id' => $b->id]);
    }
    public function test_payment_is_not_recorded_before_completion_or_by_patient(): void {
        $patient = $this->user(UserRole::Patient); $ops = $this->user(UserRole::OpsAdmin);
        $b = $this->completedBooking($patient); $b->forceFill(['status' => 'CONFIRMED'])->save();
        $this->actingAs($ops, 'sanctum')->postJson("/api/v1/admin/bookings/{$b->id}/payment", [
            'status' => 'PAID', 'method' => 'CASH',
        ])->assertStatus(422);
        $this->actingAs($patient, 'sanctum')->postJson("/api/v1/admin/bookings/{$b->id}/payment", [
            'status' => 'PAID', 'method' => 'CASH',
        ])->assertStatus(404);
        $this->assertSame('UNPAID', $b->fresh()->payment_status);
    }
    public function test_payment_status_is_final_and_cannot_be_overwritten(): void {
        $patient = $this->user(UserRole::Patient); $ops = $this->user(UserRole::OpsAdmin);
        $b = $this->completedBooking($patient); $url = "/api/v1/admin/bookings/{$b->id}/payment";
        $this->actingAs($ops, 'sanctum')->postJson($url, ['status' => 'PAID', 'method' => 'CASH'])->assertOk();
        $this->actingAs($ops, 'sanctum')->postJson($url, ['status' => 'CANCELLED'])->assertStatus(422);
        $this->assertDatabaseCount('booking_payment_events', 1);
    }
    public function test_other_patient_cannot_read_payment_history(): void {
        $patient = $this->user(UserRole::Patient); $other = $this->user(UserRole::Patient);
        $b = $this->completedBooking($patient);
        $this->actingAs($other, 'sanctum')->getJson("/api/v1/bookings/{$b->id}/payment")->assertStatus(404);
    }
}