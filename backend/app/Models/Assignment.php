<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
class Assignment extends Model {
    use HasUlids;
    protected $fillable = ['booking_id', 'partner_id', 'scheduled_start', 'active', 'accepted_at'];
    protected function casts(): array {
        return ['scheduled_start' => 'datetime', 'accepted_at' => 'datetime', 'active' => 'boolean'];
    }
}
