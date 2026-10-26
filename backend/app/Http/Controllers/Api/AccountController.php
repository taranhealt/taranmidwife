<?php
namespace App\Http\Controllers\Api;
use App\Enums\PartnerVerificationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\PartnerDocument;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class AccountController extends Controller {
    public const TERMINAL = ['COMPLETED', 'CANCELLED', 'UNFULFILLED'];
    public const PLACEHOLDER = '[DIHAPUS]';
    public function destroy(Request $request): JsonResponse {
        $data = $request->validate(['password' => ['required', 'string']]);
        $auth = $request->user();
        if (! in_array($auth->role, [UserRole::Patient, UserRole::Partner], true)) {
            AuditEvent::record('account.delete', $auth->id, 'user', (string) $auth->id, 'failure');
            return response()->json(['code' => 'STAFF_ACCOUNT',
                'message' => 'Akun staf tidak dapat dihapus sendiri. Hubungi administrator sistem.'], 403);
        }
        if (! Hash::check($data['password'], $auth->password)) {
            AuditEvent::record('account.delete', $auth->id, 'user', (string) $auth->id, 'failure');
            return response()->json(['code' => 'INVALID_PASSWORD', 'message' => 'Kata sandi tidak sesuai.'], 403);
        }
        $profileId = null;
        $blocked = DB::transaction(function () use ($auth, &$profileId) {
            $user = User::where('id', $auth->id)->lockForUpdate()->firstOrFail();
            $hasActive = Booking::where('patient_id', $user->id)->whereNotIn('status', self::TERMINAL)->exists()
                || DB::table('assignments')->join('bookings', 'bookings.id', '=', 'assignments.booking_id')
                    ->where('assignments.partner_id', $user->id)->where('assignments.active', true)
                    ->whereNotIn('bookings.status', self::TERMINAL)->exists();
            if ($hasActive) { return true; }

            Booking::where('patient_id', $user->id)->get()->each(function ($b) {
                $b->forceFill(['address_line' => self::PLACEHOLDER, 'contact_phone' => self::PLACEHOLDER,
                    'landmark' => null, 'cancel_reason' => null, 'patient_lat' => null, 'patient_lng' => null])->save();
            });

            $profile = PartnerProfile::where('user_id', $user->id)->lockForUpdate()->first();
            if ($profile) {
                $profileId = $profile->id;
                $from = $profile->verification_status->value;
                PartnerDocument::where('partner_profile_id', $profile->id)->delete();
                $profile->forceFill(['nik' => null, 'sip_number' => null, 'sip_issuer' => null, 'sip_expires_on' => null,
                    'practice_area' => null, 'review_reason' => null, 'base_lat' => null, 'base_lng' => null,
                'service_radius_km' => null, 'accepting_requests' => false,
                    'verification_status' => PartnerVerificationStatus::from('SUSPENDED')])->save();
                DB::table('partner_verification_events')->insert(['id' => (string) Str::ulid(),
                    'partner_profile_id' => $profile->id, 'from_status' => $from, 'to_status' => 'SUSPENDED',
                    'actor_id' => $user->id, 'reason' => 'Akun dihapus oleh pemilik', 'occurred_at' => now()]);
            }

            $user->tokens()->delete();
            $user->forceFill(['name' => 'Pengguna Dihapus', 'email' => "deleted-{$user->id}@deleted.invalid",
                'phone' => null, 'password' => Str::random(64), 'remember_token' => null,
                'email_verified_at' => null, 'status' => 'deleted'])->save();
            AuditEvent::record('account.delete', $user->id, 'user', (string) $user->id, 'success');
            return false;
        });
        if ($blocked) {
            AuditEvent::record('account.delete', $auth->id, 'user', (string) $auth->id, 'failure');
            return response()->json(['code' => 'ACTIVE_BOOKINGS',
                'message' => 'Masih ada pesanan yang belum selesai. Batalkan atau selesaikan dulu sebelum menghapus akun.'], 409);
        }
        if ($profileId) { Storage::disk('local')->deleteDirectory("partner-documents/{$profileId}"); }
        return response()->json(['message' => 'Akun dihapus.']);
    }
}
