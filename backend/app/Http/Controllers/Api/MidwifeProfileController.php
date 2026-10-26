<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\PartnerDocument;
use App\Models\PartnerProfile;
use App\Services\PartnerVerificationWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class MidwifeProfileController extends Controller {
    public const EDITABLE = ['DRAFT', 'NEEDS_CORRECTION', 'EXPIRED'];
    public const DOC_TYPES = ['SIP', 'STR', 'IDENTITY', 'FACE_PHOTO'];
    private function profile(Request $r): PartnerProfile {
        return PartnerProfile::where('user_id', $r->user()->id)->firstOrFail();
    }
    private function assertEditable(PartnerProfile $p): void {
        if (! in_array($p->verification_status->value, self::EDITABLE, true)) {
            abort(response()->json(['code' => 'PROFILE_LOCKED', 'message' => 'Data tidak dapat diubah pada status ini.'], 409));
        }
    }
    private function present(PartnerProfile $p): array {
        $nik = $p->nik;
        return [
            'verification_status' => $p->verification_status->value,
            'nik' => $nik ? str_repeat('*', max(strlen($nik) - 4, 0)) . substr($nik, -4) : null,
            'sip_number' => $p->sip_number, 'sip_issuer' => $p->sip_issuer,
            'sip_expires_on' => $p->sip_expires_on?->toDateString(), 'practice_area' => $p->practice_area,
            'review_reason' => $p->verification_status->value === 'NEEDS_CORRECTION' ? $p->review_reason : null,
            'documents' => $p->documents()->get(['type', 'mime', 'size_bytes'])->all(),
        ];
    }
    public function show(Request $request): JsonResponse {
        return response()->json(['data' => $this->present($this->profile($request))]);
    }
    public function update(Request $request): JsonResponse {
        $p = $this->profile($request);
        $this->assertEditable($p);
        $data = $request->validate([
            'nik' => ['sometimes', 'digits:16'],
            'sip_number' => ['sometimes', 'string', 'max:60'],
            'sip_issuer' => ['sometimes', 'string', 'max:120'],
            'sip_expires_on' => ['sometimes', 'date', 'after:today'],
            'practice_area' => ['sometimes', 'string', 'max:120'],
        ]);
        $p->fill($data)->save();
        AuditEvent::record('partner.profile.update', $request->user()->id, 'partner', $p->id, 'success');
        return response()->json(['data' => $this->present($p->fresh())]);
    }
    public function upload(Request $request): JsonResponse {
        $p = $this->profile($request);
        $this->assertEditable($p);
        $data = $request->validate([
            'type' => ['required', Rule::in(self::DOC_TYPES)],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        $file = $request->file('file');
        $mime = $file->getMimeType();
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'][$mime] ?? null;
        if (! $ext || ($data['type'] === 'FACE_PHOTO' && $ext === 'pdf')) {
            throw ValidationException::withMessages(['file' => 'Format berkas tidak didukung.']);
        }
        $oldKey = PartnerDocument::where('partner_profile_id', $p->id)->where('type', $data['type'])->value('storage_key');
        $key = Storage::disk('local')->putFileAs("partner-documents/{$p->id}", $file, Str::ulid() . '.' . $ext);
        if (! $key) { abort(500, 'Gagal menyimpan berkas.'); }
        PartnerDocument::updateOrCreate(
            ['partner_profile_id' => $p->id, 'type' => $data['type']],
            ['storage_key' => $key, 'mime' => $mime, 'size_bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath())]
        );
        if ($oldKey && $oldKey !== $key) { Storage::disk('local')->delete($oldKey); }
        AuditEvent::record('partner.document.upload', $request->user()->id, 'partner', $p->id, 'success');
        return response()->json(['data' => ['type' => $data['type'], 'mime' => $mime, 'size_bytes' => $file->getSize()]], 201);
    }
    public function submit(Request $request, PartnerVerificationWorkflow $wf): JsonResponse {
        $user = $request->user();
        $result = DB::transaction(function () use ($request, $wf, $user) {
            $p = PartnerProfile::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $wf->assertAllowed($p, 'SUBMITTED');
            $missing = [];
            foreach (['nik', 'sip_number', 'sip_issuer', 'sip_expires_on', 'practice_area'] as $f) {
                if (blank($p->$f)) { $missing[] = $f; }
            }
            $have = $p->documents()->pluck('type')->all();
            foreach (self::DOC_TYPES as $t) {
                if (! in_array($t, $have, true)) { $missing[] = 'document:' . $t; }
            }
            if ($missing) { return ['missing' => $missing]; }
            $p->submitted_at = now();
            $wf->transition($p, 'SUBMITTED', $user->id);
            return ['profile' => $p];
        });
        if (isset($result['missing'])) {
            return response()->json(['code' => 'INCOMPLETE_PROFILE', 'message' => 'Data atau dokumen belum lengkap.', 'missing' => $result['missing']], 422);
        }
        return response()->json(['data' => $this->present($result['profile'])]);
    }
}
