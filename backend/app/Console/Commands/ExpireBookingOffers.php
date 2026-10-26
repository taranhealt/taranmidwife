<?php
namespace App\Console\Commands;
use App\Services\OfferDispatcher;
use Illuminate\Console\Command;
class ExpireBookingOffers extends Command {
    protected $signature = 'bookings:expire-offers';
    protected $description = 'Proses tawaran mitra yang kedaluwarsa dan tawarkan ulang booking yang menunggu mitra';
    public function handle(OfferDispatcher $dispatcher): int {
        $r = $dispatcher->expireOffers();
        $this->info("Tawaran kedaluwarsa diproses: {$r['expired']}; booking menunggu diproses: {$r['swept']}");
        return self::SUCCESS;
    }
}
