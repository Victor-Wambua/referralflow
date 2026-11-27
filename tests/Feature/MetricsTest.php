<?php

use App\Domain\EscalationService;
use App\Domain\MetricsService;
use App\Domain\ReferralService;
use App\Enums\Role;
use App\Models\AuditEntry;
use App\Models\Referral;
use App\Models\UserNotice;
use App\Security\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Hand-computed fixture. Week of Monday 2026-03-02. All referrals come from facility R.
 * X receives 11 sent referrals, Y receives 2 (suppressed: fewer than 5).
 *
 * minutes after sent -> ack | decision (accept or decline) | completion
 *  1 completed  10 | 30 | 600      2 completed 20 | 50 | 1000     3 completed 40 | 70 | 1400
 *  4 declined   30 | 90            5 declined  50 | 110
 *  6 cancelled by referrer before any response
 *  7 cancelled after accept (did not attend)  15 | 25
 *  8 accepted    5 | 45           9 scheduled 60 | 100
 * 10 sent (no response)           11 received 25 | -        12 draft (never sent)
 * Y: 13 accepted 12 | 35,  14 accepted 18 | 55
 */
function mk(array $a)
{
    static $n = 0;
    $n++;
    $R = test()->R;
    $sent = isset($a['sent']) ? Carbon::parse('2026-03-02 09:00:00')->addMinutes($a['sent']) : null;
    $at = fn ($m) => $m === null ? null : $sent->copy()->addMinutes($m);
    $row = [
        'referring_facility_id' => $R->id, 'receiving_facility_id' => ($a['to'] ?? test()->X)->id, 'created_by' => test()->creator->id,
        'priority' => 'urgent', 'reason_code' => 'medical', 'status' => $a['status'],
        'sent_at' => $sent, 'received_at' => $at($a['ack'] ?? null),
        'accepted_at' => $at($a['acc'] ?? null), 'declined_at' => $at($a['dec'] ?? null), 'completed_at' => $at($a['done'] ?? null),
        'cancelled_at' => $at($a['canc'] ?? null), 'terminal_reason' => $a['reason'] ?? null,
        'created_at' => $a['created'] ?? ($sent ? $sent->copy()->subMinutes(5) : Carbon::parse('2026-03-02 08:00:00')),
        'updated_at' => now(),
    ];

    return TenantContext::asSystem(function () use ($row) {
        $r = new Referral;
        $r->forceFill($row);
        $r->save();

        return $r;
    });
}

beforeEach(function () {
    $this->R = facility(['name' => 'Referrer R']);
    $this->X = facility(['name' => 'Beta X']);
    $this->Y = facility(['name' => 'Alpha Y']);
    $this->creator = user(Role::Clinician, $this->R);
    mk(['status' => 'completed', 'sent' => 0, 'ack' => 10, 'acc' => 30, 'done' => 600]);
    mk(['status' => 'completed', 'sent' => 60, 'ack' => 20, 'acc' => 50, 'done' => 1000]);
    mk(['status' => 'completed', 'sent' => 120, 'ack' => 40, 'acc' => 70, 'done' => 1400]);
    mk(['status' => 'declined', 'sent' => 180, 'ack' => 30, 'dec' => 90, 'reason' => 'no_capacity']);
    mk(['status' => 'declined', 'sent' => 240, 'ack' => 50, 'dec' => 110, 'reason' => 'service_unavailable']);
    mk(['status' => 'cancelled', 'sent' => 300, 'canc' => 20, 'reason' => 'created_in_error']);
    mk(['status' => 'cancelled', 'sent' => 360, 'ack' => 15, 'acc' => 25, 'canc' => 600, 'reason' => 'patient_did_not_attend']);
    mk(['status' => 'accepted', 'sent' => 420, 'ack' => 5, 'acc' => 45]);
    mk(['status' => 'scheduled', 'sent' => 480, 'ack' => 60, 'acc' => 100]);
    mk(['status' => 'sent', 'sent' => 540]);
    mk(['status' => 'received', 'sent' => 600, 'ack' => 25]);
    mk(['status' => 'created', 'created' => Carbon::parse('2026-03-03 10:00:00')]);
    mk(['status' => 'accepted', 'sent' => 660, 'ack' => 12, 'acc' => 35, 'to' => $this->Y]);
    mk(['status' => 'accepted', 'sent' => 720, 'ack' => 18, 'acc' => 55, 'to' => $this->Y]);
    // outside the period: must not count
    mk(['status' => 'completed', 'sent' => -60 * 24 * 30, 'ack' => 1, 'acc' => 2, 'done' => 3]);
    $this->from = Carbon::parse('2026-03-02 00:00:00');
    $this->to = Carbon::parse('2026-03-09 00:00:00');
    app('auth')->forgetGuards();
});

it('computes network metrics reproducibly from the fixture (hand-computed values)', function () {
    expect(Carbon::parse('2026-03-02')->isMonday())->toBeTrue();
    $m = MetricsService::compute($this->from, $this->to);
    $o = $m['overall'];
    expect($o['created'])->toBe(14)           // 13 sent + 1 draft created in the week; the old one is outside
        ->and($o['sent'])->toBe(13)
        ->and($o['pending'])->toBe(2)         // #10 sent, #11 received
        ->and($o['in_progress'])->toBe(4)     // #8, #13, #14 accepted + #9 scheduled
        ->and($o['accepted'])->toBe(8)        // #1,2,3,7,8,9,13,14 have an acceptance time
        ->and($o['declined'])->toBe(2)
        ->and($o['cancelled'])->toBe(2)
        ->and($o['completed'])->toBe(3)
        ->and($o['terminal'])->toBe(7)
        ->and($o['median_ack_minutes'])->toBe(20.0)         // 5,10,12,15,18,20,25,30,40,50,60
        ->and($o['median_decision_minutes'])->toBe(52.5)    // 25,30,35,45,50,55,70,90,100,110
        ->and($o['median_completion_minutes'])->toBe(1000.0)
        ->and($o['acceptance_rate'])->toBe(0.8)             // 8 / (8 + 2)
        ->and($o['incomplete_rate'])->toBe(4 / 7);          // (2 declined + 2 cancelled) / 7 terminal
    expect($m['reasons'])->toBe(['cancelled:created_in_error' => 1, 'cancelled:patient_did_not_attend' => 1, 'declined:no_capacity' => 1, 'declined:service_unavailable' => 1]);
    expect($m['weekly'])->toBe(['2026-03-02' => 13]);
});

it('computes per-facility figures, suppresses small groups and lists alphabetically', function () {
    $m = MetricsService::compute($this->from, $this->to);
    expect(array_column($m['facilities'], 'facility'))->toBe(['Alpha Y', 'Beta X']); // alphabetical, not ranked
    [$y, $x] = $m['facilities'];
    expect($y['suppressed'])->toBeTrue()->and($y['received_n'])->toBe(2)->and($y)->not->toHaveKey('acceptance_rate');
    expect($x['suppressed'])->toBeFalse()->and($x['received_n'])->toBe(11)
        ->and($x['acceptance_rate'])->toBe(0.75)                  // 6 accepted / (6 + 2 declined)
        ->and($x['median_decision_minutes'])->toBe(60.0)          // 25,30,45,50,70,90,100,110
        ->and($x['completion_rate'])->toBe(3 / 7)
        ->and($x['pending'])->toBe(2);
    config(['referralflow.metrics.min_cell' => 2]);
    expect(MetricsService::compute($this->from, $this->to)['facilities'][0]['suppressed'])->toBeFalse();
    config(['referralflow.metrics.min_cell' => 12]);
    expect(array_column(MetricsService::compute($this->from, $this->to)['facilities'], 'suppressed'))->toBe([true, true]);
});

it('scopes a facility view to referrals where that facility is a party', function () {
    $m = MetricsService::compute($this->from, $this->to, $this->X->id);
    expect($m['overall']['sent'])->toBe(11)->and($m['overall']['accepted'])->toBe(6)->and($m['overall']['median_ack_minutes'])->toBe(25.0)
        ->and($m['overall']['incomplete_rate'])->toBe(4 / 7);
    $r = MetricsService::compute($this->from, $this->to, $this->R->id);
    expect($r['overall']['sent'])->toBe(13);
    $none = MetricsService::compute($this->from, $this->to, facility()->id);
    expect($none['overall']['sent'])->toBe(0)->and($none['overall']['incomplete_rate'])->toBeNull()->and($none['overall']['median_ack_minutes'])->toBeNull();
});

it('handles an empty period without errors', function () {
    $m = MetricsService::compute(Carbon::parse('2030-01-01'), Carbon::parse('2030-01-08'));
    expect($m['overall']['sent'])->toBe(0)->and($m['overall']['acceptance_rate'])->toBeNull()->and($m['facilities'])->toBe([])->and($m['weekly'])->toBe([]);
    expect(MetricsService::median([]))->toBeNull()->and(MetricsService::median([3]))->toBe(3.0)->and(MetricsService::median([1, 2, 3, 4]))->toBe(2.5);
});

it('shows aggregates only to the analyst with cautions and no ranking or identifiers', function () {
    Carbon::setTestNow('2026-03-08 12:00:00');
    $analyst = user(Role::NetworkAnalyst);
    $res = signedIn($this, $analyst)->get('/dashboard?days=30')->assertOk();
    $res->assertSee('Read with care')->assertSee('Hidden: too few referrals')->assertSee('57%')->assertSee('75%')->assertSee('Aggregates only');
    $res->assertDontSee('Rank')->assertDontSee('Best');
    $csv = signedIn($this, $analyst)->post('/dashboard/export?days=30')->assertOk();
    expect($csv->headers->get('Content-Type'))->toContain('text/csv');
    expect($csv->getContent())->toContain('incomplete_rate,0.571')->toContain('sent,13');
    expect(AuditEntry::query()->where('action', 'dashboard.export')->where('flag', 'export')->count())->toBe(1);
    Carbon::setTestNow();
});

it('limits a coordinator’s dashboard to their own facility', function () {
    Carbon::setTestNow('2026-03-08 12:00:00');
    $coord = user(Role::Coordinator, $this->X);
    $res = signedIn($this, $coord)->get('/dashboard')->assertOk()->assertSee('your facility');
    expect($res->getContent())->toContain('<b>11</b><span>Sent</span>');
    Carbon::setTestNow();
});

// ---- escalation of unacknowledged referrals ----

it('escalates unacknowledged referrals at the SLA thresholds, once per level, and never changes their status', function () {
    Mail::fake();
    TenantContext::asSystem(fn () => Referral::query()->delete()); // isolate from the metrics fixture
    Carbon::setTestNow('2026-04-01 10:00:00');
    app('auth')->forgetGuards();
    $A = facility();
    $B = facility();
    $clin = user(Role::Clinician, $A);
    $coord = user(Role::Coordinator, $B);
    $bAdmin = user(Role::FacilityAdmin, $B);
    $aClin2 = user(Role::Clinician, $A);
    $r = sentReferral($clin, $B, ['priority' => 'emergency']);        // ack 15 min, overdue 120 min
    $routine = sentReferral($clin, $B, ['priority' => 'routine', 'patient' => patientFor($clin)]); // ack 1440
    app('auth')->forgetGuards();

    expect(EscalationService::run(now()->addMinutes(10)))->toBe(0);
    expect(EscalationService::run(now()->addMinutes(16)))->toBe(1);   // level 1: reminder to receiving facility
    expect(TenantContext::asSystem(fn () => $r->fresh()->escalation_level))->toBe(1);
    expect(UserNotice::query()->where('user_id', $coord->id)->where('kind', 'referral_overdue')->count())->toBe(1);
    expect(EscalationService::run(now()->addMinutes(20)))->toBe(0);    // idempotent
    expect(EscalationService::run(now()->addMinutes(31)))->toBe(1);   // level 2 (2 x ack)
    expect(UserNotice::query()->where('user_id', $bAdmin->id)->where('kind', 'referral_escalated')->count())->toBe(1);
    expect(UserNotice::query()->where('user_id', $clin->id)->where('kind', 'referral_escalated')->count())->toBe(1);
    expect(UserNotice::query()->where('user_id', $aClin2->id)->where('kind', 'referral_escalated')->count())->toBe(1);
    expect(EscalationService::run(now()->addMinutes(121)))->toBe(1);  // level 3: overdue
    $row = TenantContext::asSystem(fn () => $r->fresh());
    expect($row->escalation_level)->toBe(3)->and($row->overdue_flagged_at)->not->toBeNull()->and($row->status)->toBe('sent'); // never auto-cancelled
    expect(UserNotice::query()->where('user_id', $clin->id)->where('kind', 'referral_overdue')->count())->toBe(1);
    // the routine one has not reached its SLA yet
    expect(TenantContext::asSystem(fn () => $routine->fresh()->escalation_level))->toBe(0);
    // a responded referral stops escalating
    app('auth')->forgetGuards();
    $this->be($coord);
    ReferralService::accept($coord, $routine->fresh());
    expect(EscalationService::run(now()->addDays(3)))->toBe(0);
    // overdue banner appears for the referrer
    signedIn($this, $clin)->get("/referrals/{$r->id}")->assertSee('No response yet');
    $this->artisan('referrals:escalate')->assertSuccessful();
    Carbon::setTestNow();
});
