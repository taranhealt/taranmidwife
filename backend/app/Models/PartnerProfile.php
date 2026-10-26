<?php
namespace App\Models;
use App\Enums\PartnerVerificationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class PartnerProfile extends Model {
    use HasUlids;
    protected $fillable = ['user_id', 'verification_status', 'nik', 'sip_number', 'sip_issuer', 'sip_expires_on', 'practice_area'];
    protected function casts(): array {
        return ['verification_status' => PartnerVerificationStatus::class, 'nik' => 'encrypted',
            'sip_expires_on' => 'date', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function documents(): HasMany { return $this->hasMany(PartnerDocument::class); }
    public function items(): HasMany { return $this->hasMany(PartnerVerificationItem::class); }
}
