<?php
namespace App\Models;
use App\Enums\PartnerVerificationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PartnerProfile extends Model {
    use HasUlids;
    protected $fillable = ['user_id', 'verification_status'];
    protected function casts(): array { return ['verification_status' => PartnerVerificationStatus::class]; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
