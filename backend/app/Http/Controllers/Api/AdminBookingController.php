<?php
namespace App\Http\Controllers\Api;
use App\Enums\PartnerVerificationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Booking;
use App\Models\User;
use App\Services\BookingWorkflow;
use App\Support\BookingPresenter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class AdminBookingController extends Controller {
    public function screen(Request $request, string $booking, BookingWorkflow $wf): JsonResponse {
        $user = $request->user();
        $b = DB::transaction(function () use ($booking, $user, $wf) {
            $b = Booking::where('id', $booking)->lockForUpdate()->firstOrFail();
            $wf->transition($b, 'WAITING_MIDWIFE', $user->id, 'Pemeriksaan administratif selesai');
            app(\App\Services\OfferDispatcher::class)->offerNext($b);
            return $b;
        });
        return response()->json(['data' => BookingPresenter::present($b->load(['service', 'area']), $user)]);
    }
    public function assign(Request $request, string $booking, BookingWorkflow $wf): JsonResponse {
        $user = $request->user();
        $data = $request->validate(['partner_id' => ['required', 'integer']]);
        try {
            $b = DB::transaction(function () use ($booking, $data, $user, $wf) {
                $b = Booking::where('id', $booking)->lockForUpdate()->firstOrFail();
                $partner = User::with('partnerProfile')->find($data['partner_id']);
                if (! $partner || $partner->role !== UserRole::Partner || $partner->status !== 'active'
                    || $partner->partnerProfile?->verification_status !== PartnerVerificationStatus::Active) {
                    throw ValidationException::withMessages(['partner_id' => 'Mitra tidak aktif atau belum terverifikasi.']);
                }
                $wf->transition($b, 'OFFERED', $user->id);
                Assignment::create(['booking_id' => $b->id, 'partner_id' => $partner->id,
                    'scheduled_start' => $b->scheduled_start, 'active' => true]);
                return $b;
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json(['code' => 'SLOT_UNAVAILABLE',
                'message' => 'Mitra sudah memiliki penugasan pada slot ini.'], 409);
        }
        return response()->json(['data' => BookingPresenter::present($b->load(['service', 'area']), $user)]);
    }
    public function cancel(Request $request, string $booking, BookingWorkflow $wf): JsonResponse {
        $user = $request->user();
        $reason = $request->validate(['reason' => ['required', 'string', 'max:200']])['reason'];
        $b = DB::transaction(function () use ($booking, $user, $wf, $reason) {
            $b = Booking::where('id', $booking)->lockForUpdate()->firstOrFail();
            $wf->transition($b, 'CANCELLED', $user->id, $reason);
            $b->cancel_reason = $reason; $b->save();
            $wf->releaseAssignment($b);
            return $b;
        });
        return response()->json(['data' => BookingPresenter::present($b->load(['service', 'area']), $user)]);
    }
}
