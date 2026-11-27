<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityService extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['available' => 'boolean', 'verified_at' => 'datetime'];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function verificationState(): string
    {
        return \App\Directory\Verification::state($this->verified_at);
    }
}
