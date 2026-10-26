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
        if ($showContact) {
            $out['address_line'] = $b->address_line;
            $out['landmark'] = $b->landmark;
            $out['contact_phone'] = $b->contact_phone;
        }
        return $out;
    }
}
