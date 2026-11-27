<?php

use App\Enums\Role;
use App\Models\AuditEntry;
use App\Models\BreakGlassGrant;
use App\Models\Referral;
use App\Models\UserNotice;
use App\Security\ReferralAccess;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->A = facility(['name' => 'A']);
    $this->B = facility(['name' => 'B']);
    $this->C = facility(['name' => 'C']);
    $this->aClin = user(Role::Clinician, $this->A);
    $this->bCoord = user(Role::Coordinator, $this->B);
    $this->cClin = user(Role::Clinician, $this->C);
    $this->cAdmin = user(Role::FacilityAdmin, $this->C);
    $this->ref = acceptedReferral($this->aClin, $this->bCoord, ['reason_text' => 'Synthetic reason text']);
    app('auth')->forgetGuards();
});

function bg($t, $user, $id, array $over = [])
{
    return signedIn($t, $user)->post("/referrals/$id/break-glass", $over + ['reason' => 'Patient collapsed at our clinic; need history urgently', 'confirm_emergency' => '1']);
}

it('shows a locked page to outsiders with no referral content', function () {
    signedIn($this, $this->cClin)->get("/referrals/{$this->ref->id}")->assertForbidden()->assertSee('Emergency access')->assertDontSee('Synthetic reason text');
});

it('grants time-limited, flagged, read-only access with a reason and confirmation', function () {
    bg($this, $this->cClin, $this->ref->id)->assertRedirect("/referrals/{$this->ref->id}");
    $g = BreakGlassGrant::query()->first();
    expect($g->user_id)->toBe($this->cClin->id)->and($g->reason)->toContain('collapsed')->and($g->expires_at->diffInMinutes($g->granted_at, true))->toBe((float) config('referralflow.break_glass.minutes'));

    $page = signedIn($this, $this->cClin)->get("/referrals/{$this->ref->id}")->assertOk()->assertSee('Emergency access: read only')->assertSee('Synthetic reason text')->assertDontSee('Synthetic Person');
    expect(ReferralAccess::level($this->cClin, $this->ref))->toBe('breakglass');

    // flagged in the audit log
    expect(AuditEntry::query()->where('action', 'break_glass.grant')->where('flag', 'break_glass')->count())->toBe(1);
    expect(AuditEntry::query()->where('action', 'referral.view')->where('flag', 'break_glass')->count())->toBe(1);
    // the facility lead is told (neutral text)
    expect(UserNotice::query()->where('user_id', $this->cAdmin->id)->where('kind', 'break_glass')->count())->toBe(1);

    // strictly read-only
    foreach (['accept', 'attend', 'withdraw-consent'] as $a) {
        signedIn($this, $this->cClin)->post("/referrals/{$this->ref->id}/$a")->assertForbidden();
    }
    signedIn($this, $this->cClin)->post("/referrals/{$this->ref->id}/cancel", ['reason' => 'other'])->assertForbidden();
    signedIn($this, $this->cClin)->post("/referrals/{$this->ref->id}/messages", ['body' => 'hello'])->assertForbidden();
    signedIn($this, $this->cClin)->post("/referrals/{$this->ref->id}/documents")->assertForbidden();
    expect($this->ref->fresh()->status)->toBe('accepted');
});

it('requires a substantive reason, the emergency confirmation, and fresh re-authentication', function () {
    bg($this, $this->cClin, $this->ref->id, ['reason' => 'too short'])->assertSessionHasErrors('reason');
    bg($this, $this->cClin, $this->ref->id, ['confirm_emergency' => null])->assertSessionHasErrors('confirm_emergency');
    $this->actingAs($this->cClin)->withSession(['mfa_ok' => true, 'reauth_at' => time() - 3600, 'last_activity' => time(), 'session_started' => time()])
        ->post("/referrals/{$this->ref->id}/break-glass", ['reason' => str_repeat('x', 30), 'confirm_emergency' => '1'])->assertRedirect('/reauth');
    expect(BreakGlassGrant::query()->count())->toBe(0);
});

it('expires after the time limit and can be revoked', function () {
    bg($this, $this->cClin, $this->ref->id);
    Carbon::setTestNow(now()->addMinutes(59));
    expect(ReferralAccess::level($this->cClin, $this->ref->fresh()))->toBe('breakglass');
    Carbon::setTestNow(now()->addMinutes(2));
    expect(ReferralAccess::level($this->cClin, $this->ref->fresh()))->toBe('none');
    signedIn($this, $this->cClin)->get("/referrals/{$this->ref->id}")->assertForbidden();
    Carbon::setTestNow();
    BreakGlassGrant::query()->update(['expires_at' => now()->addHour(), 'revoked_at' => now()]);
    expect(ReferralAccess::level($this->cClin, $this->ref->fresh()))->toBe('none');
});

it('exposes the referral to the list only while a grant is active', function () {
    $this->be($this->cClin);
    expect(Referral::query()->count())->toBe(0);
    bg($this, $this->cClin, $this->ref->id);
    $this->be($this->cClin);
    expect(Referral::query()->count())->toBe(1);
});

it('does not apply to parties, other roles, unknown ids, or clinicians of no facility', function () {
    bg($this, $this->aClin, $this->ref->id)->assertSessionHasErrors('reason'); // already a party
    bg($this, $this->cAdmin, $this->ref->id)->assertForbidden();
    bg($this, $this->cClin, str_repeat('0', 26))->assertSessionHasErrors('reason');
    expect(BreakGlassGrant::query()->count())->toBe(0);
    expect(AuditEntry::query()->where('action', 'break_glass.denied')->count())->toBe(2);
});

it('lets a facility lead review (but not an outsider, twice, or the grantee), and records it', function () {
    bg($this, $this->cClin, $this->ref->id);
    $g = BreakGlassGrant::query()->first();
    $other = user(Role::FacilityAdmin, $this->A);
    signedIn($this, $other)->post("/admin/break-glass/{$g->id}/review", ['outcome' => 'appropriate', 'note' => 'x'])->assertForbidden();
    signedIn($this, $this->cAdmin)->post("/admin/break-glass/{$g->id}/review", ['outcome' => 'maybe', 'note' => 'x'])->assertSessionHasErrors('outcome');
    signedIn($this, $this->cAdmin)->post("/admin/break-glass/{$g->id}/review", ['outcome' => 'inappropriate', 'note' => 'No clinical need'])->assertSessionHasNoErrors();
    $g->refresh();
    expect($g->review_outcome)->toBe('inappropriate')->and($g->reviewed_by)->toBe($this->cAdmin->id);
    signedIn($this, $this->cAdmin)->post("/admin/break-glass/{$g->id}/review", ['outcome' => 'appropriate', 'note' => 'again'])->assertForbidden();
    expect(AuditEntry::query()->where('action', 'break_glass.review')->count())->toBe(1);
    signedIn($this, $this->cAdmin)->get('/admin/break-glass')->assertOk()->assertSee('No clinical need');
});

it('allows documents and FHIR export under break-glass, all flagged', function () {
    bg($this, $this->cClin, $this->ref->id);
    signedIn($this, $this->cClin)->get("/api/v1/referrals/{$this->ref->id}/fhir")->assertOk();
    expect(AuditEntry::query()->where('action', 'fhir.export')->where('flag', 'break_glass')->count())->toBe(1);
});
