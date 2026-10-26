<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
class Service extends Model {
    use HasUlids;
    protected $fillable = ['code', 'name', 'description', 'scope', 'limitations', 'price_amount', 'active'];
    protected function casts(): array { return ['active' => 'boolean']; }
}
