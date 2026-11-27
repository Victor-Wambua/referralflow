<?php

namespace App\Models;

use App\Directory\Verification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = ['capabilities' => 'array', 'is_active' => 'boolean', 'info_verified_at' => 'datetime'];

    /** @return HasMany<FacilityService, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(FacilityService::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** verified | due | stale | unverified, from the facility's own verification timestamp. */
    public function verificationState(): string
    {
        return Verification::state($this->info_verified_at);
    }
}
