<?php
namespace App\Services;
use App\Enums\PartnerVerificationStatus;
use App\Models\AuditEvent;
use App\Models\PartnerProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class PartnerVerificationWorkflow {
    public const ALLOWED = [
        'DRAFT' => ['SUBMITTED'],
        'SUBMITTED' => ['IN_REVIEW'],
        'NEEDS_CORRECTION' => ['SUBMITTED'],
        'IN_REVIEW' => ['NEEDS_CORRECTION', 'APPROVED', 'REJECTED'],
        'APPROVED' => ['ACTIVE', 'SUSPENDED'],
        'ACTIVE' => ['SUSPENDED', 'EXPIRED'],
        'SUSPENDED' => ['ACTIVE', 'REJECTED'],
        'EXPIRED' => ['SUBMITTED'],
        'REJECTED' => [],
    ];
    public function assertAllowed(PartnerProfile $p, string $to): void {
        $from = $p->verification_status->value;
        if (! in_array($to, self::ALLOWED[$from] ?? [], true)) {
            abort(response()->json(['code' => 'INVALID_TRANSITION',
                'message' => "Perubahan status verifikasi dari {$from} ke {$to} tidak diizinkan."], 409));
        }
    }
    /** Panggil di dalam DB::transaction dengan baris profil yang di-lock. */
    public function transition(PartnerProfile $p, string $to, ?int $actorId, ?string $reason = null): void {
        $this->assertAllowed($p, $to);
        $from = $p->verification_status->value;
        $p->verification_status = PartnerVerificationStatus::from($to);
        if ($to !== 'SUBMITTED') {
            $p->review_reason = $reason;
            $p->reviewed_by = $actorId ?? $p->reviewed_by;
            $p->reviewed_at = now();
        }
        $p->save();
        DB::table('partner_verification_events')->insert(['id' => (string) Str::ulid(), 'partner_profile_id' => $p->id,
            'from_status' => $from, 'to_status' => $to, 'actor_id' => $actorId, 'reason' => $reason, 'occurred_at' => now()]);
        AuditEvent::record('partner.' . strtolower($to), $actorId, 'partner', $p->id, 'success');
    }
}
