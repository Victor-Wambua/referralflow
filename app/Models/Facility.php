<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['capabilities' => 'array', 'is_active' => 'boolean', 'info_verified_at' => 'datetime'];
    }

    public function services(): HasMany
    {
        return $this->hasMany(FacilityService::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** verified | due | stale | unverified, from the facility's own verification timestamp. */
    public function verificationState(): string
    {
        return \App\Directory\Verification::state($this->info_verified_at);
    }
}
