<?php

namespace App\Security;

use App\Models\BreakGlassGrant;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The one place that decides who may see a referral and how much.
 *
 *  none       no access
 *  minimal    receiving facility after Sent, before Accepted: priority, reason, sex and age only
 *  full       referring facility always; receiving facility from Accepted onward
 *  tombstone  receiving facility after consent is withdrawn: reference and status only
 *  breakglass read-only full access under an active, logged, time-limited grant
 */
final class ReferralAccess
{
    public const NONE = 'none';
    public const MINIMAL = 'minimal';
    public const FULL = 'full';
    public const TOMBSTONE = 'tombstone';
    public const BREAKGLASS = 'breakglass';

    public static function constrain(Builder $q): void
    {
        if (TenantContext::isSystem()) {
            return;
        }
        $u = TenantContext::user();
        if (! $u || ! $u->isClinical() || ! $u->facility_id || ! $u->isActive()) {
            $q->whereRaw('1 = 0');

            return;
        }
        $fid = $u->facility_id;
        $t = $q->getModel()->getTable();
        $q->where(function (Builder $w) use ($fid, $u, $t) {
            $w->where("$t.referring_facility_id", $fid)
                ->orWhere(fn (Builder $r) => $r->where("$t.receiving_facility_id", $fid)->whereNotNull("$t.sent_at"))
                ->orWhereIn("$t.id", BreakGlassGrant::query()->active()->where('user_id', $u->id)->select('referral_id'));
        });
    }

    public static function level(?User $u, Referral $r): string
    {
        if (! $u || ! $u->isClinical() || ! $u->facility_id || ! $u->isActive()) {
            return self::NONE;
        }
        if ($r->referring_facility_id === $u->facility_id) {
            return self::FULL;
        }
        if ($r->receiving_facility_id === $u->facility_id && $r->sent_at !== null) {
            $withdrawn = $r->consent()->withoutGlobalScopes()->whereNotNull('withdrawn_at')->exists();
            if ($withdrawn) {
                return self::TOMBSTONE;
            }

            return $r->accepted_at !== null ? self::FULL : self::MINIMAL;
        }
        if (BreakGlassGrant::query()->active()->where('user_id', $u->id)->where('referral_id', $r->id)->exists()) {
            return self::BREAKGLASS;
        }

        return self::NONE;
    }

    /** Which side of the referral is this user on? referrer | receiver | null */
    public static function side(?User $u, Referral $r): ?string
    {
        if (! $u || ! $u->facility_id) {
            return null;
        }
        if ($r->referring_facility_id === $u->facility_id) {
            return 'referrer';
        }
        if ($r->receiving_facility_id === $u->facility_id) {
            return 'receiver';
        }

        return null;
    }

    /** Resolve an id without leaking existence: returns [referral|null, level]. */
    public static function locate(?User $u, string $id): array
    {
        $r = TenantContext::asSystem(fn () => Referral::query()->find($id));
        if (! $r) {
            return [null, self::NONE];
        }
        $level = self::level($u, $r);

        return $level === self::NONE ? [null, self::NONE] : [$r, $level];
    }
}
