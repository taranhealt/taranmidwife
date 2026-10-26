<?php
namespace App\Console\Commands;
use App\Models\PartnerProfile;
use App\Services\PartnerVerificationWorkflow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class ExpirePartnerSip extends Command {
    protected $signature = 'partners:expire-sip';
    protected $description = 'Ubah mitra ACTIVE dengan SIP kedaluwarsa menjadi EXPIRED';
    public function handle(PartnerVerificationWorkflow $wf): int {
        $n = 0;
        PartnerProfile::where('verification_status', 'ACTIVE')->whereDate('sip_expires_on', '<', today())
            ->pluck('id')->each(function ($id) use ($wf, &$n) {
                DB::transaction(function () use ($id, $wf, &$n) {
                    $p = PartnerProfile::where('id', $id)->lockForUpdate()->first();
                    if ($p && $p->verification_status->value === 'ACTIVE') {
                        $wf->transition($p, 'EXPIRED', null, 'SIP kedaluwarsa');
                        $n++;
                    }
                });
            });
        $this->info("Mitra diubah menjadi EXPIRED: {$n}");
        return self::SUCCESS;
    }
}
