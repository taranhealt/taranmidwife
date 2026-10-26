<?php
namespace App\Services;
use App\Models\AuditEvent;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
/** Saring otomatis (sakelar TARAN_AUTO_SCREEN): REQUESTED -> WAITING_MIDWIFE, lalu tawaran otomatis. */
class AutoScreener {
    public function __construct(private BookingWorkflow $wf, private OfferDispatcher $dispatcher) {}

    public function run(string $bookingId): void {
        DB::transaction(function () use ($bookingId) {
            $b = Booking::where('id', $bookingId)->lockForUpdate()->first();
            if (! $b || $b->status !== 'REQUESTED') { return; }
            $this->wf->transition($b, 'WAITING_MIDWIFE', null, 'Saring otomatis');
            AuditEvent::record('booking.auto_screen', null, 'booking', $b->id, 'success');
            $this->dispatcher->offerNext($b);
        });
    }
}
