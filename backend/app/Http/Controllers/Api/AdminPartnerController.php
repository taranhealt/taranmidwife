<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\PartnerDocument;
use App\Models\PartnerProfile;
use App\Models\PartnerVerificationItem;
use App\Services\PartnerVerificationWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
class AdminPartnerController extends Controller {
    public const ITEM_TYPES = ['IDENTITY', 'PROFESSIONAL_STATUS', 'IHS', 'SIP', 'FACE_PHOTO'];
    public function index(Request $request): JsonResponse {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);
        $page = PartnerProfile::with('user:id,name')->orderByDesc('created_at')->paginate($perPage);
        return response()->json($page->through(fn ($p) => [
            'id' => $p->id, 'name' => $p->user->name, 'verification_status' => $p->verification_status->value,
        ]));
    }
    private function detail(PartnerProfile $p): array {
        $nik = $p->nik;
        $items = $p->items()->get();
        return [
            'id' => $p->id, 'name' => $p->user->name, 'verification_status' => $p->verification_status->value,
            'nik' => $nik ? str_repeat('*', max(strlen($nik) - 4, 0)) . substr($nik, -4) : null,
            'sip_number' => $p->sip_number, 'sip_issuer' => $p->sip_issuer,
            'sip_expires_on' => $p->sip_expires_on?->toDateString(), 'practice_area' => $p->practice_area,
            'submitted_at' => $p->submitted_at?->toIso8601String(), 'review_reason' => $p->review_reason,
            'documents' => $p->documents()->get(['id', 'type', 'mime', 'size_bytes'])->all(),
            'checks' => $items->map(fn ($i) => ['item_type' => $i->item_type, 'result' => $i->result, 'method' => $i->method])->all(),
            'limitations' => $items->where('result', 'unverified_limited')->pluck('item_type')->values()->all(),
        ];
    }
    public function show(Request $request, string $partner): JsonResponse {
        $p = PartnerProfile::with('user:id,name')->findOrFail($partner);
        AuditEvent::record('partner.view', $request->user()->id, 'partner', $p->id, 'success');
        return response()->json(['data' => $this->detail($p)]);
    }
    public function document(Request $request, string $partner, string $document) {
        $doc = PartnerDocument::where('partner_profile_id', $partner)->where('id', $document)->firstOrFail();
        AuditEvent::record('partner.document.view', $request->user()->id, 'partner', $partner, 'success');
        $resp = Storage::disk('local')->download($doc->storage_key, strtolower($doc->type) . '.' . pathinfo($doc->storage_key, PATHINFO_EXTENSION));
        $resp->headers->set('Cache-Control', 'no-store, private');
        return $resp;
    }
    private function act(Request $request, string $partner, callable $fn): JsonResponse {
        $user = $request->user();
        $p = DB::transaction(function () use ($partner, $fn, $user) {
            $p = PartnerProfile::where('id', $partner)->lockForUpdate()->firstOrFail();
            $fn($p, $user);
            return $p;
        });
        return response()->json(['data' => $this->detail($p->fresh()->load('user:id,name'))]);
    }
    public function startReview(Request $request, string $partner, PartnerVerificationWorkflow $wf): JsonResponse {
        return $this->act($request, $partner, fn ($p, $u) => $wf->transition($p, 'IN_REVIEW', $u->id));
    }
    public function recordChecks(Request $request, string $partner): JsonResponse {
        $rules = ['checks' => ['required', 'array']];
        foreach (self::ITEM_TYPES as $t) {
            $rules["checks.$t.result"] = ['required', Rule::in(['verified', 'unverified_limited', 'failed'])];
            $rules["checks.$t.method"] = ['required', 'string', 'max:120'];
        }
        $data = $request->validate($rules);
        return $this->act($request, $partner, function ($p, $u) use ($data) {
            if ($p->verification_status->value !== 'IN_REVIEW') {
                abort(response()->json(['code' => 'NOT_IN_REVIEW', 'message' => 'Pemeriksaan hanya dapat dicatat saat IN_REVIEW.'], 409));
            }
            foreach (self::ITEM_TYPES as $t) {
                PartnerVerificationItem::updateOrCreate(
                    ['partner_profile_id' => $p->id, 'item_type' => $t],
                    ['result' => $data['checks'][$t]['result'], 'method' => $data['checks'][$t]['method'],
                     'reviewer_id' => $u->id, 'checked_at' => now()]
                );
            }
            AuditEvent::record('partner.checks.recorded', $u->id, 'partner', $p->id, 'success');
        });
    }
    public function requestCorrection(Request $request, string $partner, PartnerVerificationWorkflow $wf): JsonResponse {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:200']])['reason'];
        return $this->act($request, $partner, fn ($p, $u) => $wf->transition($p, 'NEEDS_CORRECTION', $u->id, $reason));
    }
    public function approve(Request $request, string $partner, PartnerVerificationWorkflow $wf): JsonResponse {
        return $this->act($request, $partner, function ($p, $u) use ($wf) {
            $wf->assertAllowed($p, 'APPROVED');
            $items = $p->items()->get();
            if ($items->count() !== count(self::ITEM_TYPES) || $items->contains('result', 'failed')) {
                abort(response()->json(['code' => 'CHECKS_INCOMPLETE',
                    'message' => 'Kelima pemeriksaan harus tercatat dan tidak ada yang gagal.'], 422));
            }
            $wf->transition($p, 'APPROVED', $u->id);
        });
    }
    public function reject(Request $request, string $partner, PartnerVerificationWorkflow $wf): JsonResponse {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:200']])['reason'];
        return $this->act($request, $partner, fn ($p, $u) => $wf->transition($p, 'REJECTED', $u->id, $reason));
    }
    public function activate(Request $request, string $partner, PartnerVerificationWorkflow $wf): JsonResponse {
        return $this->act($request, $partner, function ($p, $u) use ($wf) {
            $wf->assertAllowed($p, 'ACTIVE');
            if (! $p->sip_expires_on || ! $p->sip_expires_on->gt(today())) {
                abort(response()->json(['code' => 'SIP_NOT_VALID', 'message' => 'SIP belum diisi atau sudah kedaluwarsa.'], 422));
            }
            $wf->transition($p, 'ACTIVE', $u->id);
        });
    }
    public function suspend(Request $request, string $partner, PartnerVerificationWorkflow $wf): JsonResponse {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:200']])['reason'];
        return $this->act($request, $partner, fn ($p, $u) => $wf->transition($p, 'SUSPENDED', $u->id, $reason));
    }
}
