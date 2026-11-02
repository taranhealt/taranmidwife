<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Booking;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
class AdminUiSupportTest extends TestCase {
    use RefreshDatabase;
    private function user(UserRole $role): User {
        $u = User::factory()->create();
        $u->forceFill(['role' => $role])->save();
        return $u;
    }
    private function booking(string $status, Service $svc, Area $area, User $patient): Booking {
        return Booking::forceCreate(['id' => (string) Str::ulid(), 'code' => 'TM-' . strtoupper(Str::random(8)),
            'patient_id' => $patient->id, 'service_id' => $svc->id, 'area_id' => $area->id, 'status' => $status,
            'scheduled_start' => Carbon::now('Asia/Jakarta')->addDays(3)->setTime(10, 0, 0)->utc(), 'price_amount' => 100000,
            'address_line' => 'Jl. Uji No. 1', 'landmark' => null, 'contact_phone' => '081234567890',
            'idempotency_key' => Str::random(20)]);
    }
    private function approvedPartner(string $sip): PartnerProfile {
        $u = $this->user(UserRole::Partner);
        return PartnerProfile::create(['user_id' => $u->id, 'verification_status' => 'APPROVED', 'sip_expires_on' => $sip]);
    }
    public function test_admin_page_loads_without_third_party_resources(): void {
        $html = $this->get('/admin')->assertOk()->assertSee('Admin')->assertSee('/js/admin.js', false)->assertSee('id="metrics"', false)
            ->assertSee('id="c-status"', false)->assertDontSee('segera rilis')->getContent();
        $this->assertFileExists(public_path('js/admin.js'));
        $this->assertStringNotContainsString('unpkg.com', $html);
        $this->assertStringNotContainsString('openstreetmap', $html);
        $this->assertStringNotContainsString('googleapis', $html);
    }
    public function test_admin_console_disables_async_action_buttons_until_request_finishes(): void {
        $js = file_get_contents(public_path('js/admin.js'));
        $this->assertStringContainsString('if (b.disabled) { return; }', $js);
        $this->assertStringContainsString("result.then(function () { b.disabled = false; }, function () { b.disabled = false; });", $js);
        $this->assertStringContainsString("return complaintAction(item.id, 'resolve'", $js);
        $this->assertStringContainsString("'/admin/bookings/' + b.id + '/payment'", $js);
        $this->assertStringContainsString('paymentControls(b, area)', $js);
    }
    public function test_booking_list_can_be_filtered_by_status(): void {
        $svc = Service::forceCreate(['id' => (string) Str::ulid(), 'code' => 'T-' . Str::random(6), 'name' => 'Layanan Uji',
            'price_amount' => 100000, 'active' => true, 'description' => '[DATA UJI]', 'limitations' => 'Uji']);
        $area = Area::forceCreate(['id' => (string) Str::ulid(), 'name' => 'Area ' . Str::random(6), 'active' => true]);
        $patient = $this->user(UserRole::Patient);
        $this->booking('REQUESTED', $svc, $area, $patient);
        $this->booking('REQUESTED', $svc, $area, $patient);
        $this->booking('COMPLETED', $svc, $area, $patient);
        $ops = $this->actingAs($this->user(UserRole::OpsAdmin), 'sanctum');
        $ops->getJson('/api/v1/bookings?status=REQUESTED')->assertOk()->assertJsonCount(2, 'data');
        $ops->getJson('/api/v1/bookings?status=COMPLETED')->assertOk()->assertJsonCount(1, 'data');
        $ops->getJson('/api/v1/bookings')->assertOk()->assertJsonCount(3, 'data');
    }
    public function test_activate_accepts_sip_expiring_today_in_wib(): void {
        $p = $this->approvedPartner(Carbon::now('Asia/Jakarta')->toDateString());
        $this->actingAs($this->user(UserRole::SysAdmin), 'sanctum')->postJson("/api/v1/admin/partners/{$p->id}/activate")
            ->assertOk()->assertJsonPath('data.verification_status', 'ACTIVE');
    }
    public function test_activate_rejects_sip_expired_yesterday_in_wib(): void {
        $p = $this->approvedPartner(Carbon::now('Asia/Jakarta')->subDay()->toDateString());
        $this->actingAs($this->user(UserRole::SysAdmin), 'sanctum')->postJson("/api/v1/admin/partners/{$p->id}/activate")
            ->assertStatus(422)->assertJsonPath('code', 'SIP_NOT_VALID');
        $this->assertSame('APPROVED', (string) DB::table('partner_profiles')->where('id', $p->id)->value('verification_status'));
    }
}
