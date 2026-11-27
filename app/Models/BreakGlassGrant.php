<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BreakGlassGrant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['granted_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function referral()
    {
        return $this->belongsTo(Referral::class)->withoutGlobalScopes();
    }
}
