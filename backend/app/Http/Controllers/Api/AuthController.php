<?php
namespace App\Http\Controllers\Api;
use App\Enums\PartnerVerificationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
class AuthController extends Controller {
    public function register(Request $request): JsonResponse {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'regex:/^\+?[0-9]{8,15}$/', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:10', 'max:100', 'confirmed'],
            'role' => ['sometimes', Rule::in(['patient', 'partner'])],
        ]);
        $user = DB::transaction(function () use ($data) {
            $user = new User(collect($data)->only(['name', 'email', 'phone', 'password'])->all());
            $user->role = UserRole::from($data['role'] ?? 'patient');
            $user->save();
            if ($user->role === UserRole::Partner) {
                PartnerProfile::create(['user_id' => $user->id, 'verification_status' => PartnerVerificationStatus::Draft]);
            }
            return $user;
        });
        AuditEvent::record('auth.register', $user->id, 'user', (string) $user->id, 'success');
        return response()->json(['user' => $this->payload($user), 'token' => $user->createToken('mobile')->plainTextToken], 201);
    }
    public function login(Request $request): JsonResponse {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', $data['email'])->first();
        // Selalu keluarkan biaya satu operasi hash, termasuk saat email tidak terdaftar,
        // agar waktu respons tidak membedakan email yang terdaftar dari yang tidak.
        if ($user) {
            $passwordOk = Hash::check($data['password'], $user->password);
        } else {
            Hash::make($data['password']);
            $passwordOk = false;
        }
        if (! $passwordOk || $user->status !== 'active') {
            AuditEvent::record('auth.login', null, null, null, 'failure');
            return response()->json(['message' => 'Email atau kata sandi tidak sesuai.'], 401);
        }
        AuditEvent::record('auth.login', $user->id, 'user', (string) $user->id, 'success');
        return response()->json(['user' => $this->payload($user), 'token' => $user->createToken('mobile')->plainTextToken]);
    }
    public function me(Request $request): JsonResponse {
        return response()->json(['user' => $this->payload($request->user())]);
    }
    public function logout(Request $request): JsonResponse {
        $user = $request->user();
        $user->currentAccessToken()->delete();
        AuditEvent::record('auth.logout', $user->id, 'user', (string) $user->id, 'success');
        return response()->json(['message' => 'Berhasil keluar.']);
    }
    private function payload(User $user): array {
        return [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role->value,
            'verification_status' => $user->role === UserRole::Partner ? $user->partnerProfile?->verification_status?->value : null,
        ];
    }
}
