<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\BookingReview;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class BookingReviewController extends Controller {
    public function show(Request $request, string $booking): JsonResponse {
        $record = Booking::whereKey($booking)->where('patient_id', $request->user()->id)->firstOrFail();
        $review = BookingReview::where('booking_id', $record->id)->first();
        return response()->json(['data' => $review ? [
            'rating' => $review->rating, 'comment' => $review->comment,
            'created_at' => $review->created_at?->toIso8601String(),
        ] : null]);
    }
    public function store(Request $request, string $booking): JsonResponse {
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:500']]);
        $patient = $request->user();
        try {
            $review = DB::transaction(function () use ($booking, $patient, $data) {
                $record = Booking::whereKey($booking)->where('patient_id', $patient->id)->lockForUpdate()->firstOrFail();
                if ($record->status !== 'COMPLETED') {
                    throw ValidationException::withMessages(['booking' => 'Ulasan hanya dapat diberikan setelah layanan selesai.']);
                }
                $assignment = $record->assignments()->where('active', true)->orderByDesc('created_at')->first();
                if (! $assignment) {
                    throw ValidationException::withMessages(['booking' => 'Mitra untuk pesanan ini tidak ditemukan.']);
                }
                if (BookingReview::where('booking_id', $record->id)->exists()) {
                    abort(response()->json(['code' => 'REVIEW_ALREADY_EXISTS', 'message' => 'Ulasan untuk pesanan ini sudah dikirim.'], 409));
                }
                $review = BookingReview::create([
                    'booking_id' => $record->id, 'patient_id' => $patient->id,
                    'partner_id' => $assignment->partner_id, 'rating' => $data['rating'],
                    'comment' => $data['comment'] ?? null,
                ]);
                AuditEvent::record('booking.review.created', $patient->id, 'booking', $record->id, 'success');
                return $review;
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json(['code' => 'REVIEW_ALREADY_EXISTS', 'message' => 'Ulasan untuk pesanan ini sudah dikirim.'], 409);
        }
        return response()->json(['data' => [
            'rating' => $review->rating, 'comment' => $review->comment,
            'created_at' => $review->created_at?->toIso8601String(),
        ]], 201);
    }
    public function partnerRating(string $partner): JsonResponse {
        User::whereKey($partner)->where('role', 'partner')->where('status', 'active')->firstOrFail();
        PartnerProfile::where('user_id', $partner)->where('verification_status', 'ACTIVE')->firstOrFail();
        $query = BookingReview::where('partner_id', $partner);
        $count = (clone $query)->count();
        $average = (clone $query)->avg('rating');
        return response()->json(['data' => [
            'review_count' => $count, 'average_rating' => $average === null ? null : round((float) $average, 1),
        ]]);
    }
}
