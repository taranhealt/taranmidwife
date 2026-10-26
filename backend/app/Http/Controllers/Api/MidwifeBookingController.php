<?php
namespace App\Http\Controllers\Api;
use App\Enums\PartnerVerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Booking;
use App\Models\User;
use App\Services\BookingWorkflow;
use App\Support\BookingPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class MidwifeBookingController extends Controller {
    private function mine(string $id, User $u): Booking {
        return Booking::where('id', $id)
            ->whereHas('assignments', fn ($q) => $q->where('partner_id', $u->id)->where('active', true))
            ->lockForUpdate()->firstOrFail();
    }
    private function ensureActive(User $u): void {
        if ($u->partnerProfile?->verification_status !== PartnerVerificationStatus::Active) {
            abort(response()->json(['code' => 'PARTNER_NOT_ACTIVE', 'message' => 'Akun mitra belum aktif.'], 403));
        }
    }
    private function run(Request $request, string $booking, string $to, BookingWorkflow $wf, bool $needsActive, ?callable $after = null): JsonResponse {
        $user = $request->user();
        $b = DB::transaction(function () use ($booking, $user, $to, $wf, $needsActive, $after) {
            $b = $this->mine($booking, $user);
            if ($needsActive) { $this->ensureActive($user); }
            $wf->transition($b, $to, $user->id);
            if ($after) { $after($b); }
            return $b;
        });
        return response()->json(['data' => BookingPresenter::present($b->load(['service', 'area']), $user)]);
    }
    public function accept(Request $request, string $booking, BookingWorkflow $wf): JsonResponse {
        return $this->run($request, $booking, 'CONFIRMED', $wf, true, fn ($b) =>
            Assignment::where('booking_id', $b->id)->where('active', true)->update(['accepted_at' => now()]));
    }
    public function decline(Request $request, string $booking, BookingWorkflow $wf): JsonResponse {
        return $this->run($request, $booking, 'WAITING_MIDWIFE', $wf, false, fn ($b) => app(\App\Services\OfferDispatcher::class)->afterDecline($b));
    }
    public function start(Request $request, string $booking, BookingWorkflow $wf): JsonResponse {
        return $this->run($request, $booking, 'IN_PROGRESS', $wf, true);
    }
    public function complete(Request $request, string $booking, BookingWorkflow $wf): JsonResponse {
        return $this->run($request, $booking, 'COMPLETED', $wf, true);
    }
}
