<?php

namespace App\Models\Concerns;

use App\Models\Referral;
use Illuminate\Database\Eloquent\Builder;

/** Child records of a referral are only visible when the parent referral is visible. */
trait ScopedByReferral
{
    protected static function bootScopedByReferral(): void
    {
        static::addGlobalScope('visible_referral', function (Builder $q) {
            $q->whereIn($q->getModel()->getTable().'.referral_id', Referral::query()->select('id'));
        });
    }
}
