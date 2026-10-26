<?php
namespace Database\Seeders;
use App\Enums\PartnerVerificationStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
/** Data SINTETIS untuk development/reviewer. Jangan dijalankan di produksi. */
class DevDemoSeeder extends Seeder {
    /** Jam slot booking (WIB), sama dengan slot di BookingController. */
    private const HOURS = [8, 10, 12, 14, 16];

    public function run(): void {
        if (app()->environment('production')) {
            throw new \RuntimeException('Seeder demo tidak boleh dijalankan di produksi.');
        }
        $password = Str::password(16, true, true, false, false);
        $accounts = [
            'patient.demo@example.test' => ['Pasien Demo', UserRole::Patient],
            'partner.demo@example.test' => ['Mitra Bidan Demo', UserRole::Partner],
            'ops.demo@example.test' => ['Admin Operasional Demo', UserRole::OpsAdmin],
            'reviewer.demo@example.test' => ['Reviewer Demo', UserRole::Reviewer],
            'sysadmin.demo@example.test' => ['Admin Sistem Demo', UserRole::SysAdmin],
        ];
        $lines = ['AKUN DEMO (data sintetis) - jangan commit, jangan dibagikan lewat chat', "Kata sandi: {$password}", ''];

        $area = Area::updateOrCreate(['name' => 'Sei Suka'], ['active' => true]);
        $serviceIds = [];
        foreach ([
            ['DEMO-NIFAS', 'Kunjungan Ibu Nifas'],
            ['DEMO-MENYUSUI', 'Konseling Menyusui'],
            ['DEMO-BAYI', 'Edukasi Perawatan Bayi Baru Lahir'],
        ] as [$code, $name]) {
            $s = Service::updateOrCreate(['code' => $code], [
                'name' => $name, 'price_amount' => 100000, 'active' => true,
                'description' => '[DATA UJI] Bukan layanan atau tarif sebenarnya; menunggu tinjauan klinis.',
                'limitations' => 'Bukan layanan gawat darurat.',
            ]);
            $serviceIds[] = $s->id;
        }

        foreach ($accounts as $email => [$name, $role]) {
            $u = User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => $password]);
            $u->forceFill(['role' => $role, 'status' => 'active'])->save();
            if ($role === UserRole::Partner) {
                $this->seedPartner($u, $area->id, $serviceIds);
            }
            $lines[] = "{$role->value}: {$email}";
        }
        Storage::disk('local')->put('demo-credentials.txt', implode("\n", $lines) . "\n");
    }

    /**
     * Mitra demo yang layak dipasangkan: aktif, SIP berlaku, semua layanan demo, wilayah Sei Suka,
     * jadwal penuh, titik layanan di titik tengah area pilot (config/taran.php; BUKAN koordinat Sei Suka
     * yang diverifikasi) dengan radius 15 km. Data sintetis.
     */
    private function seedPartner(User $u, string $areaId, array $serviceIds): void {
        $p = PartnerProfile::updateOrCreate(['user_id' => $u->id], ['verification_status' => PartnerVerificationStatus::Active]);
        $p->forceFill([
            'sip_number' => 'DEMO-SIP-0001', 'sip_issuer' => 'Dinas Kesehatan (data uji)',
            'sip_expires_on' => now()->addYear()->toDateString(), 'accepting_requests' => true,
            'base_lat' => (float) config('taran.pilot.center_lat'), 'base_lng' => (float) config('taran.pilot.center_lng'),
            'service_radius_km' => 15,
        ])->save();
        foreach ($serviceIds as $sid) {
            DB::table('partner_services')->insertOrIgnore(['partner_profile_id' => $p->id, 'service_id' => $sid]);
        }
        DB::table('partner_areas')->insertOrIgnore(['partner_profile_id' => $p->id, 'area_id' => $areaId]);
        $rows = [];
        foreach (range(0, 6) as $d) {
            foreach (self::HOURS as $h) { $rows[] = ['partner_profile_id' => $p->id, 'weekday' => $d, 'hour' => $h]; }
        }
        DB::table('partner_availabilities')->insertOrIgnore($rows);
    }
}
