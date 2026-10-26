<?php
namespace App\Services;
use App\Models\AuditEvent;
use App\Models\Complaint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class ComplaintWorkflow {
    public const ALLOWED = [
        'OPEN' => ['IN_REVIEW'],
        'IN_REVIEW' => ['RESOLVED'],
        'RESOLVED' => ['CLOSED', 'IN_REVIEW'],
        'CLOSED' => [],
    ];
    /** Panggil di dalam DB::transaction dengan baris pengaduan yang di-lock. */
    public function transition(Complaint $c, string $to, int $actorId, ?string $note = null): void {
        $from = $c->status;
        if (! in_array($to, self::ALLOWED[$from] ?? [], true)) {
            abort(response()->json(['code' => 'INVALID_TRANSITION',
                'message' => "Perubahan status pengaduan dari {$from} ke {$to} tidak diizinkan."], 409));
        }
        $c->status = $to;
        if ($to === 'IN_REVIEW') { $c->handler_id = $actorId; $c->resolved_at = null; }
        if ($to === 'RESOLVED') { $c->resolution_note = $note; $c->resolved_at = now(); }
        $c->save();
        DB::table('complaint_events')->insert(['id' => (string) Str::ulid(), 'complaint_id' => $c->id,
            'from_status' => $from, 'to_status' => $to, 'actor_id' => $actorId, 'occurred_at' => now()]);
        AuditEvent::record('complaint.' . strtolower($to), $actorId, 'complaint', $c->id, 'success');
    }
}
