<?php

namespace App\Domain;

use App\Models\Facility;
use App\Models\Referral;
use App\Security\TenantContext;
use Illuminate\Support\Carbon;

/**
 * Aggregate-only metrics computed from referral rows (no identifiers are ever selected).
 *
 * Cohort = referrals SENT in [from, to). Definitions (also in docs/USER-GUIDE.md):
 *  pending            status sent or received (no decision yet)
 *  in_progress        accepted, scheduled or attended
 *  median_ack         median minutes from sent to received
 *  median_decision    median minutes from sent to accepted-or-declined (first decision)
 *  median_completion  median minutes from sent to completed
 *  acceptance_rate    accepted / (accepted + declined) among decided referrals
 *  incomplete_rate    (declined + cancelled) / (completed + declined + cancelled) among terminal referrals
 * Cells with fewer than metrics.min_cell referrals are suppressed in facility comparisons.
 */
final class MetricsService
{
    private const COLS = ['id', 'referring_facility_id', 'receiving_facility_id', 'priority', 'reason_code', 'status', 'created_at', 'sent_at', 'received_at', 'accepted_at', 'declined_at', 'cancelled_at', 'completed_at', 'terminal_reason', 'terminal_side'];

    /**
     * @param  ?int  $facilityId  null = whole network; otherwise referrals where this facility is a party
     */
    public static function compute(Carbon $from, Carbon $to, ?int $facilityId = null): array
    {
        $rows = TenantContext::asSystem(function () use ($from, $to, $facilityId) {
            $q = Referral::query()->select(self::COLS)->where(function ($w) use ($from, $to) {
                $w->whereBetween('sent_at', [$from, $to->copy()->subSecond()])
                    ->orWhereBetween('created_at', [$from, $to->copy()->subSecond()]);
            });
            if ($facilityId) {
                $q->where(fn ($w) => $w->where('referring_facility_id', $facilityId)->orWhere('receiving_facility_id', $facilityId));
            }

            return $q->get();
        });

        $created = $rows->filter(fn ($r) => $r->created_at >= $from && $r->created_at < $to)->count();
        $cohort = $rows->filter(fn ($r) => $r->sent_at && $r->sent_at >= $from && $r->sent_at < $to)->values();

        $t = fn (string $status) => $cohort->where('status', $status)->count();
        $accepted = $cohort->whereNotNull('accepted_at')->count();
        $declined = $cohort->whereNotNull('declined_at')->count();
        $terminal = $t('completed') + $t('declined') + $t('cancelled');
        $min = fn ($a, $b) => $b ? ($b->timestamp - $a->timestamp) / 60 : null;

        $overall = [
            'created' => $created,
            'sent' => $cohort->count(),
            'pending' => $t('sent') + $t('received'),
            'in_progress' => $t('accepted') + $t('scheduled') + $t('attended'),
            'accepted' => $accepted,
            'declined' => $declined,
            'cancelled' => $t('cancelled'),
            'completed' => $t('completed'),
            'median_ack_minutes' => self::median($cohort->map(fn ($r) => $min($r->sent_at, $r->received_at))->filter(fn ($v) => $v !== null)->all()),
            'median_decision_minutes' => self::median($cohort->map(fn ($r) => $min($r->sent_at, $r->accepted_at ?? $r->declined_at))->filter(fn ($v) => $v !== null)->all()),
            'median_completion_minutes' => self::median($cohort->map(fn ($r) => $min($r->sent_at, $r->completed_at))->filter(fn ($v) => $v !== null)->all()),
            'acceptance_rate' => ($accepted + $declined) > 0 ? $accepted / ($accepted + $declined) : null,
            'incomplete_rate' => $terminal > 0 ? ($t('declined') + $t('cancelled')) / $terminal : null,
            'terminal' => $terminal,
        ];

        $reasons = [];
        foreach ($cohort->whereIn('status', ['declined', 'cancelled']) as $r) {
            $k = $r->status.':'.($r->terminal_reason ?? 'unspecified');
            $reasons[$k] = ($reasons[$k] ?? 0) + 1;
        }
        ksort($reasons);

        $weeks = [];
        foreach ($cohort as $r) {
            $w = $r->sent_at->copy()->startOfWeek()->toDateString();
            $weeks[$w] = ($weeks[$w] ?? 0) + 1;
        }
        ksort($weeks);

        $minCell = (int) config('referralflow.metrics.min_cell');
        $names = Facility::query()->pluck('name', 'id');
        $byFacility = [];
        foreach ($cohort->groupBy('receiving_facility_id') as $fid => $group) {
            $n = $group->count();
            $row = ['facility_id' => (int) $fid, 'facility' => $names[$fid] ?? '?', 'received_n' => $n, 'suppressed' => $n < $minCell];
            if (! $row['suppressed']) {
                $acc = $group->whereNotNull('accepted_at')->count();
                $dec = $group->whereNotNull('declined_at')->count();
                $term = $group->whereIn('status', ['completed', 'declined', 'cancelled'])->count();
                $row += [
                    'acceptance_rate' => ($acc + $dec) > 0 ? $acc / ($acc + $dec) : null,
                    'median_decision_minutes' => self::median($group->map(fn ($r) => $min($r->sent_at, $r->accepted_at ?? $r->declined_at))->filter(fn ($v) => $v !== null)->all()),
                    'completion_rate' => $term > 0 ? $group->where('status', 'completed')->count() / $term : null,
                    'pending' => $group->whereIn('status', ['sent', 'received'])->count(),
                ];
            }
            $byFacility[] = $row;
        }
        usort($byFacility, fn ($a, $b) => strcmp($a['facility'], $b['facility'])); // alphabetical: never a ranking

        return ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'overall' => $overall, 'reasons' => $reasons, 'weekly' => $weeks, 'facilities' => $byFacility, 'min_cell' => $minCell];
    }

    public static function median(array $values): ?float
    {
        $v = array_values(array_map('floatval', $values));
        if (! $v) {
            return null;
        }
        sort($v);
        $n = count($v);
        $mid = intdiv($n, 2);

        return $n % 2 ? $v[$mid] : ($v[$mid - 1] + $v[$mid]) / 2;
    }
}
