<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
class BookingReview extends Model {
    use HasUlids;
    protected $fillable = ['booking_id', 'patient_id', 'partner_id', 'rating', 'comment'];
    protected function casts(): array { return ['rating' => 'integer', 'comment' => 'encrypted']; }
}
