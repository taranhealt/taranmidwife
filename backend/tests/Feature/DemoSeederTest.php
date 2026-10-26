<?php
namespace Tests\Feature;
use App\Models\Area;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use App\Services\PartnerMatcher;
use Database\Seeders\DevDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
class DemoSeederTest extends TestCase {
    use RefreshDatabase;
    public function test_demo_partner_is_eligible_for_demo_booking_and_seeding_is_repeatable(): void {
        Storage::fake('local');
        $this->seed(DevDemoSeeder::class);
        $this->seed(DevDemoSeeder::class);
        $partner = User::where('email', 'partner.demo@example.test')->firstOrFail();
        $patient = User::where('email', 'patient.demo@example.test')->firstOrFail();
        $svc = Service::where('code', 'DEMO-NIFAS')->firstOrFail();
        $area = Area::where('name', 'Sei Suka')->firstOrFail();
        $when = Carbon::now('Asia/Jakarta')->addDays(3)->setTime(10, 0, 0);
        $b = Booking::forceCreate(['id' => (string) Str::ulid(), 'code' => 'TM-' . strtoupper(Str::random(8)),
            'patient_id' => $patient->id, 'service_id' => $svc->id, 'area_id' => $area->id, 'status' => 'WAITING_MIDWIFE',
            'scheduled_start' => $when->copy()->utc(), 'price_amount' => 100000, 'address_line' => 'Jl. Uji No. 1',
            'landmark' => null, 'contact_phone' => '081234567890', 'idempotency_key' => Str::random(20),
            'patient_lat' => (string) config('taran.pilot.center_lat'), 'patient_lng' => (string) config('taran.pilot.center_lng')]);
        $ids = array_map('intval', array_column(app(PartnerMatcher::class)->candidates($b), 'partner_id'));
        $this->assertSame([(int) $partner->id], $ids);
    }
}
