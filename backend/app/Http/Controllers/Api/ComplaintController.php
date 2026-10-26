<?php
namespace App\Http\Controllers\Api;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Complaint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class ComplaintController extends Controller {
    private function relatedBooking(Request $request, string $id): Booking {
        $u = $request->user();
        $q = Booking::where('id', $id);
        if ($u->role === UserRole::Patient) {
            $q->where('patient_id', $u->id);
        } else {
            $q->whereHas('assignments', fn ($a) => $a->where('partner_id', $u->id));
        }
        return $q->firstOrFail();
    }
    public function store(Request $request): JsonResponse {
        $u = $request->user();
        $data = $request->validate([
            'booking_id' => ['required', 'string', 'size:26'],
            'category' => ['required', Rule::in(Complaint::CATEGORIES)],
            'description' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $booking = $this->relatedBooking($request, $data['booking_id']);
        $c = DB::transaction(function () use ($u, $booking, $data) {
            Booking::where('id', $booking->id)->lockForUpdate()->first();
            $dup = Complaint::where('booking_id', $booking->id)->where('reporter_id', $u->id)
                ->where('category', $data['category'])->whereIn('status', ['OPEN', 'IN_REVIEW'])->exists();
            if ($dup) { return null; }
            $c = new Complaint(['booking_id' => $booking->id, 'reporter_id' => $u->id,
                'category' => $data['category'], 'description' => $data['description']]);
            $c->safety_flag = $data['category'] === 'SAFETY';
            $c->save();
            DB::table('complaint_events')->insert(['id' => (string) Str::ulid(), 'complaint_id' => $c->id,
                'from_status' => null, 'to_status' => 'OPEN', 'actor_id' => $u->id, 'occurred_at' => now()]);
            AuditEvent::record('complaint.create', $u->id, 'complaint', $c->id, 'success');
            return $c;
        });
        if (! $c) {
            return response()->json(['code' => 'DUPLICATE_COMPLAINT',
                'message' => 'Pengaduan dengan kategori yang sama untuk pesanan ini masih diproses.'], 409);
        }
        $body = ['data' => $c->load('booking:id,code')->toApi(false)];
        if ($c->safety_flag) {
            $body['notice'] = 'Jika ini keadaan darurat, segera hubungi layanan gawat darurat setempat. Pengaduan ini tidak menggantikan bantuan darurat.';
        }
        return response()->json($body, 201);
    }
    public function index(Request $request): JsonResponse {
        $u = $request->user();
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);
        $page = Complaint::where('reporter_id', $u->id)->with('booking:id,code')->orderByDesc('created_at')->paginate($perPage);
        return response()->json($page->through(fn ($c) => $c->toApi(false)));
    }
    public function show(Request $request, string $complaint): JsonResponse {
        $c = Complaint::where('id', $complaint)->where('reporter_id', $request->user()->id)
            ->with('booking:id,code')->firstOrFail();
        return response()->json(['data' => $c->toApi(false)]);
    }
}
