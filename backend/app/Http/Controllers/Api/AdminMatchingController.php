<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Services\PartnerMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class AdminMatchingController extends Controller {
    public function candidates(Request $request, string $booking, PartnerMatcher $matcher): JsonResponse {
        $b = Booking::findOrFail($booking);
        if (! in_array($b->status, ['REQUESTED', 'WAITING_MIDWIFE'], true)) {
            return response()->json(['code' => 'BOOKING_NOT_MATCHABLE',
                'message' => 'Pesanan pada status ini tidak dapat dicocokkan dengan mitra.'], 409);
        }
        AuditEvent::record('booking.candidates.view', $request->user()->id, 'booking', $b->id, 'success');
        $c = $matcher->candidates($b);
        return response()->json(['data' => $c, 'meta' => ['count' => count($c)]]);
    }
}
