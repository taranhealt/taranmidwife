<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
class Area extends Model {
    use HasUlids;
    protected $fillable = ['name', 'active'];
    protected function casts(): array { return ['active' => 'boolean']; }
}
