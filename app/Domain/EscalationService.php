<?php

namespace App\Domain;

use App\Enums\Role;
use App\Models\Referral;
use App\Security\AuditLog;
use App\Security\TenantContext;

/**
 * Unacknowledged referrals escalate; they are never silently changed or auto-cancelled.
 * level 1: ack SLA passed  -> remind receiving facility
 * level 2: 2 x ack SLA     -> remind again, include facility admin, prompt referrer to phone
 * level 3: overdue limit   -> flag overdue, prompt referrer to consider another facility
 */
final class EscalationService
{
    public static function run(?\DateTimeInterface $now = null): int
    {
        $now = \Illuminate\Support\Carbon::instance($now ?? now());
        $count = 0;
        TenantContext::asSystem(function () use ($now, &$count) {
            Referral::query()->whereIn('status', ['sent', 'received'])->whereNotNull('sent_at')->get()->each(function (Referral $r) use ($now, &$count) {
                $sla = config('referralflow.sla.'.$r->priority);
                $age = $r->sent_at->diffInMinutes($now, true);
                $target = match (true) {
                    $age >= $sla['overdue'] => 3,
                    $age >= 2 * $sla['ack'] => 2,
                    $age >= $sla['ack'] => 1,
                    default => 0,
                };
                if ($target <= $r->escalation_level) {
                    return;
                }
                $r->forceFill(['escalation_level' => $target, 'last_escalated_at' => $now] + ($target === 3 ? ['overdue_flagged_at' => $now] : []))->save();
                if ($target === 1) {
                    Notifier::toFacility($r->receiving_facility_id, 'referral_overdue', $r);
                } elseif ($target === 2) {
                    Notifier::toFacility($r->receiving_facility_id, 'referral_escalated', $r, [Role::Coordinator, Role::FacilityAdmin]);
                    Notifier::toFacility($r->referring_facility_id, 'referral_escalated', $r);
                } else {
                    Notifier::toFacility($r->referring_facility_id, 'referral_overdue', $r);
                    Notifier::toFacility($r->receiving_facility_id, 'referral_escalated', $r, [Role::Coordinator, Role::FacilityAdmin]);
                }
                AuditLog::record('referral.escalate', 'referral', $r->id, ['level' => $target]);
                $count++;
            });
        });

        return $count;
    }
}
