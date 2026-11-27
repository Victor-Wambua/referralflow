<?php

namespace App\Models;

use App\Directory\Verification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityService extends Model
{
    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = ['available' => 'boolean', 'verified_at' => 'datetime'];

    /** @return BelongsTo<Facility, $this> */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function verificationState(): string
    {
        return Verification::state($this->verified_at);
    }
}
