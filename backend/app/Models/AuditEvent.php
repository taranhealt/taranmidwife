<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
class AuditEvent extends Model {
    use HasUlids;
    public $timestamps = false;
    protected $fillable = ['actor_id', 'action', 'entity_type', 'entity_id', 'outcome'];
    public static function record(string $action, ?int $actorId, ?string $type, ?string $id, string $outcome): self {
        return static::create(['actor_id' => $actorId, 'action' => $action, 'entity_type' => $type, 'entity_id' => $id, 'outcome' => $outcome]);
    }
}
