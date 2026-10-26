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
        $data = $request->validate([
            'partner_id' => ['required', 'integer'],
            'override' => ['sometimes', 'boolean'],
            'override_reason' => ['required_if_accepted:override', 'nullable', 'string', 'max:200'],
        ]);
        try {
            $b = DB::transaction(function () use ($booking, $data, $user, $wf) {
                $b = Booking::where('id', $booking)->lockForUpdate()->firstOrFail();
                $partner = User::with('partnerProfile')->find($data['partner_id']);
                if (! $partner || $partner->role !== UserRole::Partner || $partner->status !== 'active'
                    || $partner->partnerProfile?->verification_status !== PartnerVerificationStatus::Active) {
                    throw ValidationException::withMessages(['partner_id' => 'Mitra tidak aktif atau belum terverifikasi.']);
                }
                $wf->transition($b, 'OFFERED', $user->id, ! empty($data['override'])
                    ? 'Override: ' . \Illuminate\Support\Str::limit((string) $data['override_reason'], 80, '') : null);
                if (empty($data['override'])) {
                    $eligible = collect(app(\App\Services\PartnerMatcher::class)->candidates($b))
                        ->contains(fn ($c) => $c['partner_id'] === (int) $partner->id);
                    if (! $eligible) {
                        throw ValidationException::withMessages(['partner_id' => 'Mitra tidak memenuhi kriteria (layanan, wilayah, jadwal, ketersediaan). Kirim override beserta alasan bila tetap ingin menugaskan.']);
                    }
                } else {
                    \App\Models\AuditEvent::record('booking.assign_override', $user->id, 'booking', $b->id, 'success');
                }
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
