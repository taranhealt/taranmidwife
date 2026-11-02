<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
class BookingPaymentEvent extends Model {
    use HasUlids;
    public $timestamps = false;
    protected $fillable = ['booking_id', 'from_status', 'to_status', 'method', 'actor_id', 'occurred_at'];
    protected function casts(): array { return ['occurred_at' => 'datetime']; }
}