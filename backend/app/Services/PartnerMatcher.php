<?php
namespace App\Services;
use App\Models\Booking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
class PartnerMatcher {
    public const TZ = 'Asia/Jakarta';

    /** Jarak haversine (km) titik layanan mitra ke titik pasien. Tiga placeholder berurutan: lat, lat, lng. */
    private static function distSql(): string {
        return '(6371 * 2 * asin(least(1, sqrt(power(sin(radians(cast(pp.base_lat as double precision) - cast(? as double precision)) / 2), 2) + cos(radians(cast(? as double precision))) * cos(radians(cast(pp.base_lat as double precision))) * power(sin(radians(cast(pp.base_lng as double precision) - cast(? as double precision)) / 2), 2)))))';
    }

    /**
     * Mitra yang layak untuk booking. Bila booking punya koordinat: hanya mitra yang titik layanannya
     * mencakup lokasi pasien, terdekat lebih dulu. Tanpa koordinat: urutan pemerataan (paling lama tidak ditawari).
     * @return array<int, array{partner_id:int, profile_id:string, name:string, last_offered_at:?string, distance_km:?float}>
     */
    public function candidates(Booking $b, array $excludePartnerIds = []): array {
        $lat = $b->patient_lat !== null ? (float) $b->patient_lat : null;
        $lng = $b->patient_lng !== null ? (float) $b->patient_lng : null;
        $geo = $lat !== null && $lng !== null;
        $tried = DB::table('assignments')->where('booking_id', $b->id)->pluck('partner_id')->all();
        $exclude = array_values(array_unique(array_map('intval', array_merge($tried, $excludePartnerIds))));
        $q = DB::table('partner_profiles as pp')
            ->join('users as u', 'u.id', '=', 'pp.user_id')
            ->where('u.role', 'partner')
            ->where('u.status', 'active')
            ->where('pp.verification_status', 'ACTIVE')
            ->where('pp.accepting_requests', true)
            ->whereNotNull('pp.sip_expires_on')
            ->whereRaw("pp.sip_expires_on >= (select (bk.scheduled_start at time zone 'Asia/Jakarta')::date from bookings bk where bk.id = ?)", [$b->id])
            ->whereExists(fn ($s) => $s->select(DB::raw(1))->from('partner_services as ps')
                ->whereColumn('ps.partner_profile_id', 'pp.id')->where('ps.service_id', $b->service_id))
            ->whereExists(fn ($s) => $s->select(DB::raw(1))->from('partner_areas as pa')
                ->whereColumn('pa.partner_profile_id', 'pp.id')->where('pa.area_id', $b->area_id))
            ->whereRaw("exists (select 1 from partner_availabilities pv, bookings bk where bk.id = ? and pv.partner_profile_id = pp.id and pv.weekday = extract(dow from (bk.scheduled_start at time zone 'Asia/Jakarta')) and pv.hour = extract(hour from (bk.scheduled_start at time zone 'Asia/Jakarta')))", [$b->id])
            ->whereRaw('not exists (select 1 from assignments a, bookings bk where bk.id = ? and a.partner_id = u.id and a.active = true and a.scheduled_start = bk.scheduled_start)', [$b->id]);
        if ($exclude) { $q->whereNotIn('u.id', $exclude); }
        $q->select('u.id as partner_id', 'pp.id as profile_id', 'u.name', 'pp.last_offered_at');
        if ($geo) {
            $q->whereNotNull('pp.base_lat')->whereRaw(self::distSql() . ' <= pp.service_radius_km', [$lat, $lat, $lng]);
            $q->selectRaw(self::distSql() . ' as distance_km', [$lat, $lat, $lng]);
            $q->orderByRaw(self::distSql() . ' asc', [$lat, $lat, $lng]);
        } else {
            $q->selectRaw('null as distance_km');
        }
        return $q->orderByRaw('pp.last_offered_at asc nulls first')->orderBy('u.id')->get()
            ->map(fn ($r) => ['partner_id' => (int) $r->partner_id, 'profile_id' => $r->profile_id, 'name' => $r->name,
                'last_offered_at' => $r->last_offered_at ? Carbon::parse($r->last_offered_at)->toIso8601String() : null,
                'distance_km' => $r->distance_km !== null ? round((float) $r->distance_km, 1) : null])
            ->all();
    }

    /** Jumlah mitra aktif (menerima permintaan, SIP berlaku hari ini) yang radiusnya mencakup titik. Tidak memeriksa jadwal. */
    public function nearbyCount(float $lat, float $lng, ?string $serviceId = null): int {
        $q = DB::table('partner_profiles as pp')
            ->join('users as u', 'u.id', '=', 'pp.user_id')
            ->where('u.role', 'partner')->where('u.status', 'active')
            ->where('pp.verification_status', 'ACTIVE')->where('pp.accepting_requests', true)
            ->whereNotNull('pp.sip_expires_on')
            ->where('pp.sip_expires_on', '>=', Carbon::now(self::TZ)->toDateString())
            ->whereNotNull('pp.base_lat')
            ->whereRaw(self::distSql() . ' <= pp.service_radius_km', [$lat, $lat, $lng]);
        if ($serviceId) {
            $q->whereExists(fn ($s) => $s->select(DB::raw(1))->from('partner_services as ps')
                ->whereColumn('ps.partner_profile_id', 'pp.id')->where('ps.service_id', $serviceId));
        }
        return $q->count();
    }
}
