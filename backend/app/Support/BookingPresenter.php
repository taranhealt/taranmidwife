<?php
namespace App\Support;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;
class BookingPresenter {
    public static function present(Booking $b, User $viewer): array {
        $showContact = match ($viewer->role) {
            UserRole::Patient => $b->patient_id === $viewer->id,
            UserRole::OpsAdmin => true,
            UserRole::Partner => in_array($b->status, ['CONFIRMED', 'IN_PROGRESS'], true),
            default => false,
        };
        $out = [
            'id' => $b->id, 'code' => $b->code, 'status' => $b->status,
            'service' => $b->service?->name, 'area' => $b->area?->name,
            'scheduled_start' => $b->scheduled_start->toIso8601String(),
            'price_amount' => $b->price_amount,
        ];
        if ($viewer->role === UserRole::Partner && $b->patient_lat !== null && $b->patient_lng !== null) {
            $sp = \App\Models\PartnerProfile::where('user_id', $viewer->id)->first(['base_lat', 'base_lng']);
            if ($sp && $sp->base_lat !== null) {
                $out['distance_km'] = round(Geo::distanceKm((float) $sp->base_lat, (float) $sp->base_lng, (float) $b->patient_lat, (float) $b->patient_lng), 1);
            }
        }
        if ($showContact) {
            $out['address_line'] = $b->address_line;
            $out['landmark'] = $b->landmark;
            $out['contact_phone'] = $b->contact_phone;
        }
        return $out;
    }
}
