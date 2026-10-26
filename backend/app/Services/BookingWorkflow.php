<?php
namespace App\Services;
use App\Models\Assignment;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
class BookingWorkflow {
    public const ALLOWED = [
        'REQUESTED' => ['WAITING_MIDWIFE', 'NEEDS_INFO', 'CANCELLED', 'UNFULFILLED'],
        'NEEDS_INFO' => ['REQUESTED', 'CANCELLED'],
        'WAITING_MIDWIFE' => ['OFFERED', 'CANCELLED', 'UNFULFILLED'],
        'OFFERED' => ['CONFIRMED', 'WAITING_MIDWIFE', 'CANCELLED'],
        'CONFIRMED' => ['IN_PROGRESS', 'CANCELLED'],
        'IN_PROGRESS' => ['COMPLETED'],
        'COMPLETED' => [], 'CANCELLED' => [], 'UNFULFILLED' => [],
    ];
    /** Panggil di dalam DB::transaction dengan baris booking yang sudah di-lock. */
    public function transition(Booking $booking, string $to, ?int $actorId, ?string $reason = null): void {
        $from = $booking->status;
        if (! in_array($to, self::ALLOWED[$from] ?? [], true)) {
            abort(response()->json([
                'code' => 'INVALID_TRANSITION',
                'message' => "Perubahan status dari {$from} ke {$to} tidak diizinkan.",
            ], 409));
        }
        $booking->status = $to;
        $booking->save();
        BookingStatusHistory::create(['booking_id' => $booking->id, 'from_status' => $from,
            'to_status' => $to, 'actor_id' => $actorId, 'reason' => $reason]);
        AuditEvent::record('booking.' . strtolower($to), $actorId, 'booking', $booking->id, 'success');
    }
    public function releaseAssignment(Booking $booking): void {
        Assignment::where('booking_id', $booking->id)->where('active', true)->update(['active' => false]);
    }
}
