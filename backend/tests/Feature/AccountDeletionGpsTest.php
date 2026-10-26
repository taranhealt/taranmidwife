<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Booking;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
class AccountDeletionGpsTest extends TestCase {
    use RefreshDatabase;
    private const PW = 'KataSandiUji123';
    protected function setUp(): void { parent::setUp(); Storage::fake('local'); }
    private function user(UserRole $role): User {
        $u = User::factory()->create();
        $u->forceFill(['role' => $role, 'password' => self::PW])->save();
        return $u;
    }
    public function test_patient_coordinates_are_removed_on_account_deletion(): void {
        $u = $this->user(UserRole::Patient);
        $svc = Service::forceCreate(['id' => (string) Str::ulid(), 'code' => 'T-' . Str::random(6), 'name' => 'Layanan Uji',
            'price_amount' => 100000, 'active' => true, 'description' => '[DATA UJI]', 'limitations' => 'Uji']);
        $area = Area::forceCreate(['id' => (string) Str::ulid(), 'name' => 'Area ' . Str::random(5), 'active' => true]);
        $b = Booking::forceCreate(['id' => (string) Str::ulid(), 'code' => 'TM-' . strtoupper(Str::random(8)),
            'patient_id' => $u->id, 'service_id' => $svc->id, 'area_id' => $area->id, 'status' => 'COMPLETED',
            'scheduled_start' => now()->addDays(2), 'price_amount' => 100000, 'address_line' => 'Jl. Uji No. 1',
            'landmark' => null, 'contact_phone' => '081234567890', 'idempotency_key' => Str::random(20),
            'patient_lat' => '3.2', 'patient_lng' => '99.5']);
        $this->assertNotNull(DB::table('bookings')->where('id', $b->id)->value('patient_lat'));
        $this->actingAs($u, 'sanctum')->deleteJson('/api/v1/me', ['password' => self::PW])->assertOk();
        $this->assertNull(DB::table('bookings')->where('id', $b->id)->value('patient_lat'));
        $this->assertNull(DB::table('bookings')->where('id', $b->id)->value('patient_lng'));
        $this->assertSame($u->id, (int) DB::table('bookings')->where('id', $b->id)->value('patient_id'));
    }
    public function test_partner_service_point_is_removed_on_account_deletion(): void {
        $u = $this->user(UserRole::Partner);
        $p = PartnerProfile::create(['user_id' => $u->id, 'verification_status' => 'ACTIVE']);
        $p->forceFill(['base_lat' => 3.2, 'base_lng' => 99.5, 'service_radius_km' => 10, 'accepting_requests' => true])->save();
        $this->actingAs($u, 'sanctum')->deleteJson('/api/v1/me', ['password' => self::PW])->assertOk();
        $row = DB::table('partner_profiles')->where('id', $p->id)->first();
        $this->assertNull($row->base_lat);
        $this->assertNull($row->base_lng);
        $this->assertNull($row->service_radius_km);
        $this->assertFalse((bool) $row->accepting_requests);
    }
}
