<?php
namespace App\Support;
class Geo {
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 6371 * 2 * asin(min(1.0, sqrt($a)));
    }
    public static function withinPilot(float $lat, float $lng): bool {
        $c = config('taran.pilot');
        return self::distanceKm($lat, $lng, (float) $c['center_lat'], (float) $c['center_lng']) <= (float) $c['radius_km'];
    }
}
