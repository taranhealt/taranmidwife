<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMail;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
class PasswordResetController extends Controller {
    public const GENERIC = 'Jika email terdaftar, tautan pengaturan ulang kata sandi telah dikirim.';
    public function forgot(Request $request): JsonResponse {
        $data = $request->validate(['email' => ['required', 'email', 'max:150']]);
        $user = User::where('email', $data['email'])->where('status', 'active')->first();
        if ($user) {
            $token = Password::broker()->createToken($user);
            $url = url('/atur-ulang-sandi') . '?token=' . urlencode($token) . '&email=' . urlencode($user->email);
            Mail::to($user->email)->send(new ResetPasswordMail($url));
            if (app()->environment('local')) {
                Log::info('[DEV-LOKAL] Tautan atur ulang kata sandi: ' . $url);
            }
            AuditEvent::record('auth.password_reset_request', $user->id, 'user', (string) $user->id, 'success');
        } else {
            AuditEvent::record('auth.password_reset_request', null, null, null, 'failure');
        }
        return response()->json(['message' => self::GENERIC]);
    }
    public function reset(Request $request): JsonResponse {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'token' => ['required', 'string', 'max:200'],
            'password' => ['required', 'string', 'min:10', 'max:100', 'confirmed'],
        ]);
        $user = User::where('email', $data['email'])->where('status', 'active')->first();
        $status = $user
            ? Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function (User $u, string $password) {
                $u->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                $u->tokens()->delete();
            })
            : Password::INVALID_TOKEN;
        if ($status !== Password::PASSWORD_RESET) {
            AuditEvent::record('auth.password_reset', $user?->id, $user ? 'user' : null, $user ? (string) $user->id : null, 'failure');
            return response()->json(['code' => 'INVALID_RESET_TOKEN',
                'message' => 'Tautan tidak valid atau sudah kedaluwarsa. Minta tautan baru.'], 422);
        }
        AuditEvent::record('auth.password_reset', $user->id, 'user', (string) $user->id, 'success');
        return response()->json(['message' => 'Kata sandi diperbarui. Silakan masuk.']);
    }
}
