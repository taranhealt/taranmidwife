<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Assignment;
use App\Models\Booking;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class BookingReviewTest extends TestCase {
    use RefreshDatabase;
    private function user(UserRole $role): User {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'status' => 'active'])->save();
        return $user;
    }
    private function completedBooking(User $patient, User $partner, string $status = 'COMPLETED'): Booking {
        $service = Service::create(['code' => 'REV-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(6)), 'name' => 'Layanan Uji', 'price_amount' => 100000, 'active' => true]);
        $area = Area::create(['name' => 'Area ' . \Illuminate\Support\Str::random(6), 'active' => true]);
        $booking = Booking::create([
            'code' => 'REV-' . strtoupper(substr((string) \Illuminate\Support\Str::ulid(), -8)),
            'patient_id' => $patient->id, 'service_id' => $service->id, 'area_id' => $area->id,
            'scheduled_start' => now()->addDay(), 'price_amount' => 100000,
            'address_line' => 'Alamat sintetis', 'contact_phone' => '081200000001',
            'idempotency_key' => 'review-' . \Illuminate\Support\Str::random(12),
        ]);
        $booking->forceFill(['status' => $status])->save();
        Assignment::create(['booking_id' => $booking->id, 'partner_id' => $partner->id,
            'scheduled_start' => now()->addDay(), 'active' => true, 'accepted_at' => now()]);
        return $booking;
    }
    public function test_patient_can_review_completed_booking_once_and_public_rating_has_no_identity(): void {
        $patient = $this->user(UserRole::Patient);
        $partner = $this->user(UserRole::Partner);
        PartnerProfile::create(['user_id' => $partner->id, 'verification_status' => 'ACTIVE']);
        $booking = $this->completedBooking($patient, $partner);
        $this->actingAs($patient, 'sanctum')->postJson("/api/v1/bookings/{$booking->id}/review",
            ['rating' => 5, 'comment' => 'Pelayanan baik dan tepat waktu.'])
            ->assertCreated()->assertJsonPath('data.rating', 5);
        $this->actingAs($patient, 'sanctum')->getJson("/api/v1/bookings/{$booking->id}/review")
            ->assertOk()->assertJsonPath('data.rating', 5);
        $this->getJson("/api/v1/partners/{$partner->id}/rating")
            ->assertOk()->assertJsonPath('data.review_count', 1)->assertJsonPath('data.average_rating', 5)
            ->assertJsonMissingPath('data.patient_id')->assertJsonMissingPath('data.comment');
        $this->assertDatabaseHas('audit_events', ['action' => 'booking.review.created', 'entity_id' => $booking->id]);
    }
    public function test_only_booking_owner_can_review_and_booking_must_be_completed(): void {
        $patient = $this->user(UserRole::Patient); $other = $this->user(UserRole::Patient);
        $partner = $this->user(UserRole::Partner);
        $booking = $this->completedBooking($patient, $partner);
        $this->actingAs($other, 'sanctum')->postJson("/api/v1/bookings/{$booking->id}/review", ['rating' => 4])->assertNotFound();
        $pending = $this->completedBooking($patient, $this->user(UserRole::Partner), 'CONFIRMED');
        $this->actingAs($patient, 'sanctum')->postJson("/api/v1/bookings/{$pending->id}/review", ['rating' => 4])->assertStatus(422);
    }
    public function test_duplicate_review_is_rejected_and_rating_validation_is_strict(): void {
        $patient = $this->user(UserRole::Patient); $partner = $this->user(UserRole::Partner);
        $booking = $this->completedBooking($patient, $partner);
        $url = "/api/v1/bookings/{$booking->id}/review";
        $this->actingAs($patient, 'sanctum')->postJson($url, ['rating' => 6])->assertStatus(422);
        $this->actingAs($patient, 'sanctum')->postJson($url, ['rating' => 4])->assertCreated();
        $this->actingAs($patient, 'sanctum')->postJson($url, ['rating' => 5])->assertStatus(409);
    }
}
