<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakGlassGrant extends Model
{
    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = ['granted_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime', 'reviewed_at' => 'datetime'];

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Referral, $this> */
    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class)->withoutGlobalScopes();
    }
}
