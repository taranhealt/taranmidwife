<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
class PartnerDocument extends Model {
    use HasUlids;
    protected $fillable = ['partner_profile_id', 'type', 'storage_key', 'mime', 'size_bytes', 'sha256'];
}
