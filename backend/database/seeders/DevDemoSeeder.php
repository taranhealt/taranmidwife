<?php
namespace Database\Seeders;
use App\Enums\PartnerVerificationStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
/** Data SINTETIS untuk development/reviewer. Jangan dijalankan di produksi. */
class DevDemoSeeder extends Seeder {
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
        foreach ($accounts as $email => [$name, $role]) {
            $u = User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => $password]);
            $u->forceFill(['role' => $role, 'status' => 'active'])->save();
            if ($role === UserRole::Partner) {
                PartnerProfile::updateOrCreate(['user_id' => $u->id], ['verification_status' => PartnerVerificationStatus::Active]);
            }
            $lines[] = "{$role->value}: {$email}";
        }
        Area::updateOrCreate(['name' => 'Sei Suka'], ['active' => true]);
        foreach ([
            ['DEMO-NIFAS', 'Kunjungan Ibu Nifas'],
            ['DEMO-MENYUSUI', 'Konseling Menyusui'],
            ['DEMO-BAYI', 'Edukasi Perawatan Bayi Baru Lahir'],
        ] as [$code, $name]) {
            Service::updateOrCreate(['code' => $code], [
                'name' => $name, 'price_amount' => 100000, 'active' => true,
                'description' => '[DATA UJI] Bukan layanan atau tarif sebenarnya; menunggu tinjauan klinis.',
                'limitations' => 'Bukan layanan gawat darurat.',
            ]);
        }
        Storage::disk('local')->put('demo-credentials.txt', implode("\n", $lines) . "\n");
    }
}
