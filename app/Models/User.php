<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $guarded = [];

    protected $hidden = ['password', 'remember_token', 'mfa_secret', 'mfa_recovery_codes'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'encrypted:array',
            'mfa_enabled_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'must_change_password' => 'boolean',
            'role' => Role::class,
        ];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isClinical(): bool
    {
        return $this->role->isClinical();
    }
}
