<?php
namespace Tests\Feature;
use App\Enums\UserRole;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class ExpireSipTimezoneTest extends TestCase {
    use RefreshDatabase;
    private function activePartner(string $sip): PartnerProfile {
        $u = User::factory()->create();
        $u->forceFill(['role' => UserRole::Partner])->save();
        $p = PartnerProfile::create(['user_id' => $u->id, 'verification_status' => 'ACTIVE']);
        $p->forceFill(['sip_expires_on' => $sip])->save();
        return $p;
    }
    private function profileStatus(PartnerProfile $p): string {
        return (string) DB::table('partner_profiles')->where('id', $p->id)->value('verification_status');
    }
    public function test_sip_expiring_today_in_wib_stays_active(): void {
        $p = $this->activePartner(Carbon::now('Asia/Jakarta')->toDateString());
        $this->artisan('partners:expire-sip')->assertSuccessful();
        $this->assertSame('ACTIVE', $this->profileStatus($p));
    }
    public function test_sip_expired_yesterday_in_wib_becomes_expired(): void {
        $p = $this->activePartner(Carbon::now('Asia/Jakarta')->subDay()->toDateString());
        $this->artisan('partners:expire-sip')->assertSuccessful();
        $this->assertSame('EXPIRED', $this->profileStatus($p));
    }
}
