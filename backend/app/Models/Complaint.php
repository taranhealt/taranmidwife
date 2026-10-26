<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Complaint extends Model {
    use HasUlids;
    public const CATEGORIES = ['SERVICE_QUALITY', 'PARTNER_CONDUCT', 'SCHEDULING', 'PRICE_OR_PAYMENT', 'PRIVACY', 'SAFETY', 'OTHER'];
    public const STATUSES = ['OPEN', 'IN_REVIEW', 'RESOLVED', 'CLOSED'];
    // status, safety_flag, handler, catatan penanganan TIDAK mass-assignable.
    protected $fillable = ['booking_id', 'reporter_id', 'category', 'description'];
    protected $attributes = ['status' => 'OPEN', 'safety_flag' => false];
    protected function casts(): array {
        return ['description' => 'encrypted', 'resolution_note' => 'encrypted', 'safety_flag' => 'boolean',
            'resolved_at' => 'datetime'];
    }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reporter_id'); }
    public function toApi(bool $admin): array {
        $out = [
            'id' => $this->id, 'booking_code' => $this->booking?->code, 'category' => $this->category,
            'description' => $this->description, 'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'resolution_note' => in_array($this->status, ['RESOLVED', 'CLOSED'], true) ? $this->resolution_note : null,
        ];
        if ($admin) {
            $out['resolution_note'] = $this->resolution_note;
            $out['safety_flag'] = $this->safety_flag;
            $out['reporter_role'] = $this->reporter?->role?->value;
            $out['handled'] = $this->handler_id !== null;
            $out['resolved_at'] = $this->resolved_at?->toIso8601String();
        }
        return $out;
    }
}
