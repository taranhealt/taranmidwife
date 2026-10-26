<?php
namespace App\Http\Controllers\Api;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingWorkflow;
use App\Support\BookingPresenter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class BookingController extends Controller {
    public const SLOT_HOURS = [8, 10, 12, 14, 16];
    public const TZ = 'Asia/Jakarta';

    public static function scoped(User $u): Builder {
        return match ($u->role) {
            UserRole::Patient => Booking::where('patient_id', $u->id),
            UserRole::Partner => Booking::whereHas('assignments',
                fn ($q) => $q->where('partner_id', $u->id)->where('active', true)),
            UserRole::OpsAdmin, UserRole::SysAdmin => Booking::query(),
            default => abort(403, 'Akses ditolak.'),
        };
    }
    public function index(Request $request): JsonResponse {
        $user = $request->user();
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);
        $page = self::scoped($user)->with(['service:id,name', 'area:id,name'])
            ->orderByDesc('created_at')->paginate($perPage);
        return response()->json($page->through(fn ($b) => BookingPresenter::present($b, $user)));
    }
    public function show(Request $request, string $booking): JsonResponse {
        $user = $request->user();
        $b = self::scoped($user)->with(['service:id,name', 'area:id,name'])->where('id', $booking)->firstOrFail();
        return response()->json(['data' => BookingPresenter::present($b, $user)]);
    }
    public function store(Request $request): JsonResponse {
        $user = $request->user();
        $key = (string) $request->header('Idempotency-Key');
        if (strlen($key) < 8 || strlen($key) > 64) {
            throw ValidationException::withMessages(['idempotency_key' => 'Header Idempotency-Key wajib diisi (8-64 karakter).']);
        }
        $existing = Booking::where('patient_id', $user->id)->where('idempotency_key', $key)->first();
        if ($existing) {
            return response()->json(['data' => BookingPresenter::present($existing->load(['service', 'area']), $user)], 200);
        }
        $data = $request->validate([
            'service_id' => ['required', 'string'],
            'area_id' => ['required', 'string'],
            'scheduled_start' => ['required', 'date'],
            'address_line' => ['required', 'string', 'max:300'],
            'landmark' => ['nullable', 'string', 'max:100'],
            'contact_phone' => ['required', 'regex:/^\+?[0-9]{8,15}$/'],
            'latitude' => [config('taran.require_gps') ? 'required' : 'nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => [config('taran.require_gps') ? 'required' : 'nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ]);
        $service = Service::where('id', $data['service_id'])->where('active', true)->first();
        $area = Area::where('id', $data['area_id'])->where('active', true)->first();
        if (! $service) { throw ValidationException::withMessages(['service_id' => 'Layanan tidak tersedia.']); }
        if (! $area) { throw ValidationException::withMessages(['area_id' => 'Wilayah belum dilayani.']); }
        if (isset($data['latitude'], $data['longitude']) && ! \App\Support\Geo::withinPilot((float) $data['latitude'], (float) $data['longitude'])) {
            return response()->json(['code' => 'OUT_OF_COVERAGE', 'message' => 'Lokasi Anda di luar area layanan pilot kami.'], 422);
        }
        $start = Carbon::parse($data['scheduled_start'])->setTimezone(self::TZ);
        if ($start->minute !== 0 || $start->second !== 0 || ! in_array($start->hour, self::SLOT_HOURS, true)) {
            throw ValidationException::withMessages(['scheduled_start' => 'Pilih slot yang tersedia (jam 08, 10, 12, 14, atau 16 WIB).']);
        }
        if ($start->isPast()) {
            throw ValidationException::withMessages(['scheduled_start' => 'Jadwal tidak boleh di masa lalu.']);
        }
        try {
            $b = Booking::create([
                'code' => 'TM-' . now()->format('ymd') . '-' . Str::upper(Str::random(6)),
                'patient_id' => $user->id, 'service_id' => $service->id, 'area_id' => $area->id,
                'scheduled_start' => $start->copy()->utc(), 'price_amount' => $service->price_amount,
                'address_line' => $data['address_line'], 'landmark' => $data['landmark'] ?? null,
                'contact_phone' => $data['contact_phone'], 'idempotency_key' => $key,
                'patient_lat' => isset($data['latitude']) ? (string) round((float) $data['latitude'], 6) : null,
                'patient_lng' => isset($data['longitude']) ? (string) round((float) $data['longitude'], 6) : null,
            ]);
        } catch (UniqueConstraintViolationException) {
            $b = Booking::where('patient_id', $user->id)->where('idempotency_key', $key)->firstOrFail();
            return response()->json(['data' => BookingPresenter::present($b->load(['service', 'area']), $user)], 200);
        }
        DB::table('booking_status_histories')->insert(['id' => (string) Str::ulid(), 'booking_id' => $b->id,
            'from_status' => null, 'to_status' => 'REQUESTED', 'actor_id' => $user->id, 'occurred_at' => now()]);
        return response()->json(['data' => BookingPresenter::present($b->load(['service', 'area']), $user),
            'notice' => 'Permintaan diterima. Jadwal belum final sampai dikonfirmasi.'], 201);
    }
    public function cancel(Request $request, string $booking, BookingWorkflow $wf): JsonResponse {
        $user = $request->user();
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:200']])['reason'] ?? null;
        $b = DB::transaction(function () use ($booking, $user, $wf, $reason) {
            $b = Booking::where('id', $booking)->where('patient_id', $user->id)->lockForUpdate()->firstOrFail();
            $wf->transition($b, 'CANCELLED', $user->id, $reason);
            $b->cancel_reason = $reason; $b->save();
            $wf->releaseAssignment($b);
            return $b;
        });
        return response()->json(['data' => BookingPresenter::present($b->load(['service', 'area']), $user)]);
    }
}
