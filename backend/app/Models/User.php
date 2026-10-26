<?php
namespace App\Models;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable {
    use HasApiTokens, HasFactory, Notifiable;
    // role dan status sengaja TIDAK mass-assignable.
    protected $fillable = ['name', 'email', 'password', 'phone'];
    protected $attributes = ['role' => 'patient', 'status' => 'active'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'role' => UserRole::class];
    }
    public function partnerProfile(): HasOne { return $this->hasOne(PartnerProfile::class); }
}
