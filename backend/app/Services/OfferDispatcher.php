<?php
namespace App\Services;
use App\Models\Assignment;
use App\Models\AuditEvent;
use App\Models\Booking;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
class OfferDispatcher {
    /** ASUMSI sementara (belum keputusan pemilik): batas waktu mitra menjawab tawaran. */
    public const OFFER_MINUTES = 10;
    public function __construct(private BookingWorkflow $wf, private PartnerMatcher $matcher) {}

    /**
     * Tawarkan ke kandidat teratas. Panggil di dalam DB::transaction dengan baris booking yang sudah di-lock.
     * Mengembalikan true bila tawaran dibuat; status booking menjadi OFFERED.
     */
    public function offerNext(Booking $b): bool {
        if ($b->status !== 'WAITING_MIDWIFE') { return false; }
        if (Assignment::where('booking_id', $b->id)->where('active', true)->exists()) { return false; }
        foreach ($this->matcher->candidates($b) as $c) {
            try {
                DB::transaction(function () use ($b, $c) {
                    Assignment::forceCreate(['booking_id' => $b->id, 'partner_id' => $c['partner_id'],
                        'scheduled_start' => $b->scheduled_start, 'active' => true, 'auto_offered' => true,
                        'offer_expires_at' => now()->addMinutes(self::OFFER_MINUTES)]);
                });
            } catch (UniqueConstraintViolationException) {
                continue; // slot mitra baru saja terisi; coba kandidat berikutnya
            }
            $this->wf->transition($b, 'OFFERED', null, 'Penawaran otomatis');
            DB::table('partner_profiles')->where('id', $c['profile_id'])->update(['last_offered_at' => now()]);
            AuditEvent::record('booking.auto_offer', null, 'booking', $b->id, 'success');
            return true;
        }
        return false;
    }

    /** Setelah mitra menolak: status sudah WAITING_MIDWIFE, penugasan masih aktif. */
    public function afterDecline(Booking $b): void {
        $a = Assignment::where('booking_id', $b->id)->where('active', true)->first();
        $auto = (bool) ($a?->auto_offered);
        $this->wf->releaseAssignment($b);
        $this->resolveOpen($b, $auto);
    }

    /** Cari mitra berikutnya; bila habis dan alurnya otomatis, tutup sebagai UNFULFILLED. */
    private function resolveOpen(Booking $b, bool $auto): void {
        if ($this->offerNext($b)) { return; }
        if ($auto) {
            $this->wf->transition($b, 'UNFULFILLED', null, 'Tidak ada mitra tersedia');
        }
    }

    /** @return array{expired:int, swept:int} */
    public function expireOffers(): array {
        $expired = 0; $swept = 0;
        $ids = DB::table('assignments')->where('active', true)->where('auto_offered', true)
            ->where('offer_expires_at', '<', now())->pluck('booking_id')->all();
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$expired) {
                $b = Booking::where('id', $id)->lockForUpdate()->first();
                if (! $b || $b->status !== 'OFFERED') { return; }
                $still = Assignment::where('booking_id', $id)->where('active', true)->where('auto_offered', true)
                    ->where('offer_expires_at', '<', now())->exists();
                if (! $still) { return; }
                $this->wf->transition($b, 'WAITING_MIDWIFE', null, 'Tawaran kedaluwarsa');
                $this->wf->releaseAssignment($b);
                $this->resolveOpen($b, true);
                $expired++;
            });
        }
        $waiting = Booking::where('status', 'WAITING_MIDWIFE')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('assignments')
                ->whereColumn('assignments.booking_id', 'bookings.id')->where('assignments.active', true))
            ->pluck('id')->all();
        foreach ($waiting as $id) {
            DB::transaction(function () use ($id, &$swept) {
                $b = Booking::where('id', $id)->lockForUpdate()->first();
                if (! $b || $b->status !== 'WAITING_MIDWIFE') { return; }
                if (Assignment::where('booking_id', $id)->where('active', true)->exists()) { return; }
                if ($b->scheduled_start->lte(now())) {
                    $this->wf->transition($b, 'UNFULFILLED', null, 'Waktu layanan terlewati tanpa mitra');
                    $swept++;
                    return;
                }
                if ($this->offerNext($b)) { $swept++; }
            });
        }
        return ['expired' => $expired, 'swept' => $swept];
    }
}
