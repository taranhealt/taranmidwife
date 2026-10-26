<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\AuditEvent;
use App\Models\PartnerProfile;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class PartnerOfferingsController extends Controller {
    private function profileId(Request $r): string {
        return PartnerProfile::where('user_id', $r->user()->id)->firstOrFail()->id;
    }
    private function present(string $pid): array {
        $avail = DB::table('partner_availabilities')->where('partner_profile_id', $pid)
            ->orderBy('weekday')->orderBy('hour')->get()->groupBy('weekday')
            ->map(fn ($rows, $w) => ['weekday' => (int) $w, 'hours' => $rows->pluck('hour')->map(fn ($h) => (int) $h)->values()->all()])
            ->values()->all();
        return [
            'accepting_requests' => (bool) DB::table('partner_profiles')->where('id', $pid)->value('accepting_requests'),
            'service_ids' => DB::table('partner_services')->where('partner_profile_id', $pid)->orderBy('service_id')->pluck('service_id')->all(),
            'area_ids' => DB::table('partner_areas')->where('partner_profile_id', $pid)->orderBy('area_id')->pluck('area_id')->all(),
            'availability' => $avail,
        ];
    }
    public function show(Request $request): JsonResponse {
        return response()->json(['data' => $this->present($this->profileId($request))]);
    }
    public function update(Request $request): JsonResponse {
        $pid = $this->profileId($request);
        $data = $request->validate([
            'service_ids' => ['sometimes', 'array', 'max:50'],
            'service_ids.*' => ['string', 'size:26', 'distinct'],
            'area_ids' => ['sometimes', 'array', 'max:50'],
            'area_ids.*' => ['string', 'size:26', 'distinct'],
            'availability' => ['sometimes', 'array', 'max:7'],
            'availability.*.weekday' => ['required', 'integer', 'between:0,6'],
            'availability.*.hours' => ['required', 'array', 'min:1', 'max:5'],
            'availability.*.hours.*' => ['integer', Rule::in(BookingController::SLOT_HOURS)],
            'accepting_requests' => ['sometimes', 'boolean'],
        ]);
        if (! empty($data['service_ids']) && Service::whereIn('id', $data['service_ids'])->where('active', true)->count() !== count($data['service_ids'])) {
            throw ValidationException::withMessages(['service_ids' => 'Ada layanan yang tidak tersedia.']);
        }
        if (! empty($data['area_ids']) && Area::whereIn('id', $data['area_ids'])->where('active', true)->count() !== count($data['area_ids'])) {
            throw ValidationException::withMessages(['area_ids' => 'Ada wilayah yang belum dilayani.']);
        }
        DB::transaction(function () use ($pid, $data) {
            DB::table('partner_profiles')->where('id', $pid)->lockForUpdate()->first();
            if (array_key_exists('service_ids', $data)) {
                DB::table('partner_services')->where('partner_profile_id', $pid)->delete();
                foreach ($data['service_ids'] as $sid) {
                    DB::table('partner_services')->insert(['partner_profile_id' => $pid, 'service_id' => $sid]);
                }
            }
            if (array_key_exists('area_ids', $data)) {
                DB::table('partner_areas')->where('partner_profile_id', $pid)->delete();
                foreach ($data['area_ids'] as $aid) {
                    DB::table('partner_areas')->insert(['partner_profile_id' => $pid, 'area_id' => $aid]);
                }
            }
            if (array_key_exists('availability', $data)) {
                DB::table('partner_availabilities')->where('partner_profile_id', $pid)->delete();
                $seen = [];
                foreach ($data['availability'] as $day) {
                    foreach ($day['hours'] as $h) {
                        $k = $day['weekday'] . '-' . $h;
                        if (isset($seen[$k])) { continue; }
                        $seen[$k] = true;
                        DB::table('partner_availabilities')->insert(['partner_profile_id' => $pid, 'weekday' => (int) $day['weekday'], 'hour' => (int) $h]);
                    }
                }
            }
            if (array_key_exists('accepting_requests', $data)) {
                DB::table('partner_profiles')->where('id', $pid)->update(['accepting_requests' => (bool) $data['accepting_requests']]);
            }
        });
        AuditEvent::record('partner.offerings.update', $request->user()->id, 'partner', $pid, 'success');
        return response()->json(['data' => $this->present($pid)]);
    }
}
