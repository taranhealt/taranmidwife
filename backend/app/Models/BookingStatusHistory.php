<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
class BookingStatusHistory extends Model {
    use HasUlids;
    public $timestamps = false;
    protected $fillable = ['booking_id', 'from_status', 'to_status', 'actor_id', 'reason'];
}
