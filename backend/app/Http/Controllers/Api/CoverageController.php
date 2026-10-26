<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\PartnerMatcher;
use App\Support\Geo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class CoverageController extends Controller {
    public function check(Request $request, PartnerMatcher $matcher): JsonResponse {
        $d = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'service_id' => ['nullable', 'string', 'size:26'],
        ]);
        $lat = (float) $d['lat'];
        $lng = (float) $d['lng'];
        $in = Geo::withinPilot($lat, $lng);
        $nearby = $in ? $matcher->nearbyCount($lat, $lng, $d['service_id'] ?? null) : 0;
        return response()->json(['data' => [
            'in_pilot_area' => $in,
            'pilot_name' => config('taran.pilot.name'),
            'partners_nearby' => $nearby,
            'serviceable' => $in && $nearby > 0,
        ]]);
    }
}
