<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Booking extends Model {
    use HasUlids;
    // status TIDAK mass-assignable: hanya berubah lewat BookingWorkflow.
    protected $fillable = ['code', 'patient_id', 'service_id', 'area_id', 'scheduled_start', 'price_amount',
        'address_line', 'landmark', 'contact_phone', 'idempotency_key', 'patient_lat', 'patient_lng'];
    protected static function booted(): void {
        static::created(function (self $b) {
            if (! config('taran.auto_screen')) { return; }
            $id = (string) $b->id;
            \Illuminate\Support\Facades\DB::afterCommit(function () use ($id) {
                try {
                    app(\App\Services\AutoScreener::class)->run($id);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('auto_screen_failed', ['booking' => $id, 'error' => $e->getMessage()]);
                }
            });
        });
    }
    protected $attributes = ['status' => 'REQUESTED'];
    protected function casts(): array {
        return ['scheduled_start' => 'datetime', 'address_line' => 'encrypted', 'contact_phone' => 'encrypted',
            'patient_lat' => 'encrypted', 'patient_lng' => 'encrypted'];
    }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function area(): BelongsTo { return $this->belongsTo(Area::class); }
    public function patient(): BelongsTo { return $this->belongsTo(User::class, 'patient_id'); }
    public function assignments(): HasMany { return $this->hasMany(Assignment::class); }
}
