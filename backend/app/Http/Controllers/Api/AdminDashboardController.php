<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\PartnerProfile;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function metrics(): JsonResponse
    {
        return response()->json(['data' => [
            'bookings_total' => Booking::query()->count(),
            'bookings_open' => Booking::query()->whereNotIn('status', ['COMPLETED', 'CANCELLED', 'UNFULFILLED'])->count(),
            'active_partners' => PartnerProfile::query()->where('verification_status', 'ACTIVE')->count(),
            'complaints_open' => Complaint::query()->whereIn('status', ['OPEN', 'IN_REVIEW'])->count(),
        ]]);
    }
}
