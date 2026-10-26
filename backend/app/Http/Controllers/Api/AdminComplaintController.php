<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Complaint;
use App\Services\ComplaintWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class AdminComplaintController extends Controller {
    public function index(Request $request): JsonResponse {
        $request->validate(['status' => ['sometimes', Rule::in(Complaint::STATUSES)], 'safety' => ['sometimes', 'boolean']]);
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);
        $q = Complaint::with(['booking:id,code', 'reporter:id,role'])->orderByDesc('created_at');
        if ($request->filled('status')) { $q->where('status', $request->query('status')); }
        if ($request->boolean('safety')) { $q->where('safety_flag', true); }
        return response()->json($q->paginate($perPage)->through(fn ($c) => $c->toApi(true)));
    }
    public function show(Request $request, string $complaint): JsonResponse {
        $c = Complaint::with(['booking:id,code', 'reporter:id,role'])->findOrFail($complaint);
        AuditEvent::record('complaint.view', $request->user()->id, 'complaint', $c->id, 'success');
        return response()->json(['data' => $c->toApi(true)]);
    }
    private function act(Request $request, string $id, callable $fn): JsonResponse {
        $u = $request->user();
        $c = DB::transaction(function () use ($id, $fn, $u) {
            $c = Complaint::where('id', $id)->lockForUpdate()->firstOrFail();
            $fn($c, $u);
            return $c;
        });
        return response()->json(['data' => $c->fresh()->load(['booking:id,code', 'reporter:id,role'])->toApi(true)]);
    }
    public function startReview(Request $request, string $complaint, ComplaintWorkflow $wf): JsonResponse {
        return $this->act($request, $complaint, fn ($c, $u) => $wf->transition($c, 'IN_REVIEW', $u->id));
    }
    public function resolve(Request $request, string $complaint, ComplaintWorkflow $wf): JsonResponse {
        $note = $request->validate(['note' => ['required', 'string', 'max:1000']])['note'];
        return $this->act($request, $complaint, fn ($c, $u) => $wf->transition($c, 'RESOLVED', $u->id, $note));
    }
    public function close(Request $request, string $complaint, ComplaintWorkflow $wf): JsonResponse {
        return $this->act($request, $complaint, fn ($c, $u) => $wf->transition($c, 'CLOSED', $u->id));
    }
    public function reopen(Request $request, string $complaint, ComplaintWorkflow $wf): JsonResponse {
        return $this->act($request, $complaint, fn ($c, $u) => $wf->transition($c, 'IN_REVIEW', $u->id));
    }
}
