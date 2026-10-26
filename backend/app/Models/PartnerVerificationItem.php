<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
class PartnerVerificationItem extends Model {
    use HasUlids;
    public $timestamps = false;
    protected $fillable = ['partner_profile_id', 'item_type', 'result', 'method', 'reviewer_id', 'checked_at'];
    protected function casts(): array { return ['checked_at' => 'datetime']; }
}
