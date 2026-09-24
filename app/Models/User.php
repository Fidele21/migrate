<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name', 'email', 'password', 'district_id',
        'employee_number', 'phone', 'is_active', 'must_change_password', 'position',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at'       => 'datetime',
            'password'                => 'hashed',
            'signature_registered_at' => 'datetime',
            'last_login_at'           => 'datetime',
            'is_active'               => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at'  => 'datetime',
        ];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function isCityWide(): bool
    {
        return $this->district_id === null;
    }

    public function coversDistrict(?int $districtId): bool
    {
        if (! $this->is_active) {
            return false;
        }
        return $this->isCityWide() || $this->district_id === $districtId;
    }
}
