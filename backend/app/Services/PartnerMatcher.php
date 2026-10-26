<?php
namespace App\Services;
use App\Models\Booking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
class PartnerMatcher {
    public const TZ = 'Asia/Jakarta';
    /**
     * Mitra yang layak untuk booking, berurutan: yang paling lama tidak ditawari lebih dulu.
     * Pencocokan hari/jam dan bentrok slot dihitung di SQL dari waktu tersimpan pada baris booking.
     * @return array<int, array{partner_id:int, profile_id:string, name:string, last_offered_at:?string}>
     */
    public function candidates(Booking $b, array $excludePartnerIds = []): array {
        $tried = DB::table('assignments')->where('booking_id', $b->id)->pluck('partner_id')->all();
        $exclude = array_values(array_unique(array_map('intval', array_merge($tried, $excludePartnerIds))));
        $q = DB::table('partner_profiles as pp')
            ->join('users as u', 'u.id', '=', 'pp.user_id')
            ->where('u.role', 'partner')
            ->where('u.status', 'active')
            ->where('pp.verification_status', 'ACTIVE')
            ->where('pp.accepting_requests', true)
            ->whereNotNull('pp.sip_expires_on')
            ->where('pp.sip_expires_on', '>', Carbon::now(self::TZ)->toDateString())
            ->whereExists(fn ($s) => $s->select(DB::raw(1))->from('partner_services as ps')
                ->whereColumn('ps.partner_profile_id', 'pp.id')->where('ps.service_id', $b->service_id))
            ->whereExists(fn ($s) => $s->select(DB::raw(1))->from('partner_areas as pa')
                ->whereColumn('pa.partner_profile_id', 'pp.id')->where('pa.area_id', $b->area_id))
            ->whereRaw("exists (select 1 from partner_availabilities pv, bookings bk where bk.id = ? and pv.partner_profile_id = pp.id and pv.weekday = extract(dow from (bk.scheduled_start at time zone 'Asia/Jakarta')) and pv.hour = extract(hour from (bk.scheduled_start at time zone 'Asia/Jakarta')))", [$b->id])
            ->whereRaw('not exists (select 1 from assignments a, bookings bk where bk.id = ? and a.partner_id = u.id and a.active = true and a.scheduled_start = bk.scheduled_start)', [$b->id]);
        if ($exclude) { $q->whereNotIn('u.id', $exclude); }
        return $q->orderByRaw('pp.last_offered_at asc nulls first')->orderBy('u.id')
            ->select('u.id as partner_id', 'pp.id as profile_id', 'u.name', 'pp.last_offered_at')->get()
            ->map(fn ($r) => ['partner_id' => (int) $r->partner_id, 'profile_id' => $r->profile_id, 'name' => $r->name,
                'last_offered_at' => $r->last_offered_at ? Carbon::parse($r->last_offered_at)->toIso8601String() : null])
            ->all();
    }
}
