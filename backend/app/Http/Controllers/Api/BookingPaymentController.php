<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPaymentEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class BookingPaymentController extends Controller {
    public function show(Request $request, string $booking): JsonResponse {
        $user = $request->user();
        $b = BookingController::scoped($user)->whereKey($booking)->firstOrFail();
        return response()->json(['data' => [
            'booking_id' => $b->id, 'amount' => $b->price_amount,
            'status' => $b->payment_status ?? 'UNPAID',
            'history' => BookingPaymentEvent::where('booking_id', $b->id)->orderBy('occurred_at')->get()
                ->map(fn ($event) => [
                    'from_status' => $event->from_status, 'to_status' => $event->to_status,
                    'method' => $event->method, 'note' => $event->note,
                    'occurred_at' => $event->occurred_at->toIso8601String(),
                ]),
        ]]);
    }
    public function update(Request $request, string $booking): JsonResponse {
        $data = $request->validate([
            'status' => ['required', 'in:PAID,CANCELLED'],
            'method' => ['required_if:status,PAID', 'nullable', 'in:CASH,BANK_TRANSFER,OTHER'],
            'note' => ['nullable', 'string', 'max:160'],
        ]);
        $actor = $request->user();
        $b = DB::transaction(function () use ($booking, $data, $actor) {
            $b = Booking::whereKey($booking)->lockForUpdate()->firstOrFail();
            $from = $b->payment_status ?? 'UNPAID';
            if ($from !== 'UNPAID') {
                throw ValidationException::withMessages(['status' => 'Status pembayaran sudah final dan tidak dapat diubah melalui pencatatan biasa.']);
            }
            if ($data['status'] === 'PAID' && $b->status !== 'COMPLETED') {
                throw ValidationException::withMessages(['status' => 'Pembayaran hanya dapat dicatat setelah layanan selesai.']);
            }
            $b->payment_status = $data['status'];
            $b->save();
            BookingPaymentEvent::create([
                'booking_id' => $b->id, 'from_status' => $from, 'to_status' => $data['status'],
                'method' => $data['method'] ?? null, 'note' => $data['note'] ?? null,
                'actor_id' => $actor->id, 'occurred_at' => now(),
            ]);
            \App\Models\AuditEvent::record('booking.payment.' . strtolower($data['status']), $actor->id, 'booking', $b->id, 'success');
            return $b;
        });
        return response()->json(['data' => [
            'booking_id' => $b->id, 'amount' => $b->price_amount, 'status' => $b->payment_status,
        ]]);
    }
}