<?php

use App\Enums\Role;
use App\Models\Referral;
use App\Models\ReferralEvent;

beforeEach(function () {
    $this->fromF = facility(['name' => 'From HC']);
    $this->toF = facility(['name' => 'To Hospital']);
    $this->clin = user(Role::Clinician, $this->fromF);
    $this->coord = user(Role::Coordinator, $this->toF);
    activeSchema();
});

function wizardPayload(array $over = []): array
{
    return $over + [
        'patient_mode' => 'new', 'full_name' => 'Amina Synthetic', 'date_of_birth' => '1991-02-03', 'sex' => 'female',
        'identifier_type' => 'facility_record', 'identifier' => 'SYN-0001', 'synthetic_confirm' => '1',
        'priority' => 'urgent', 'reason_code' => 'medical', 'reason_text' => 'Needs specialist review',
        'include_note' => '1', 'clinical_note' => 'Synthetic note', 'form' => ['treatment_given' => 'Fluids', 'allergies' => 'none'],
        'receiving_facility_id' => test()->toF->id, 'consent_basis' => 'written', 'consent_confirm' => '1', 'action' => 'send',
    ];
}

it('walks the whole happy path: create with consent, send, receive, accept, schedule, attend, complete with feedback', function () {
    signedIn($this, $this->clin)->post('/referrals', wizardPayload())->assertRedirect();
    $r = Referral::query()->first();
    expect($r->status)->toBe('sent')->and($r->consent)->not->toBeNull();

    signedIn($this, $this->coord)->get("/referrals/{$r->id}")->assertOk();
    expect($r->fresh()->status)->toBe('received'); // opening marks it received
    signedIn($this, $this->coord)->post("/referrals/{$r->id}/accept")->assertRedirect();
    signedIn($this, $this->coord)->post("/referrals/{$r->id}/schedule", ['appointment_at' => now()->addDays(2)->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
    expect($r->fresh()->status)->toBe('scheduled');
    signedIn($this, $this->coord)->post("/referrals/{$r->id}/attend")->assertRedirect();
    signedIn($this, $this->coord)->post("/referrals/{$r->id}/complete", ['outcome' => 'treated_discharged', 'summary' => 'Seen and treated'])->assertSessionHasNoErrors();

    $r->refresh();
    expect($r->status)->toBe('completed')->and($r->counterReferral->summary)->toBe('Seen and treated');
    expect(ReferralEvent::query()->where('referral_id', $r->id)->orderBy('id')->pluck('to_status')->all())
        ->toBe(['created', 'sent', 'received', 'accepted', 'scheduled', 'attended', 'completed']);

    signedIn($this, $this->clin)->get("/referrals/{$r->id}")->assertOk()->assertSee('Seen and treated');
});

it('declines with a required reason and tells the referrer', function () {
    $r = sentReferral($this->clin, $this->toF);
    signedIn($this, $this->coord)->post("/referrals/{$r->id}/decline", [])->assertSessionHasErrors('reason');
    expect($r->fresh()->status)->toBe('sent');
    signedIn($this, $this->coord)->post("/referrals/{$r->id}/decline", ['reason' => 'no_capacity', 'note' => 'Try Bonde'])->assertSessionHasNoErrors();
    $r->refresh();
    expect($r->status)->toBe('declined')->and($r->terminal_reason)->toBe('no_capacity')->and($r->terminal_note)->toBe('Try Bonde');
    signedIn($this, $this->clin)->get("/referrals/{$r->id}")->assertSee('No capacity or bed');
});

it('cancels with a reason from either side, including did not attend', function () {
    $r = acceptedReferral($this->clin, $this->coord);
    signedIn($this, $this->coord)->post("/referrals/{$r->id}/cancel", ['reason' => 'patient_did_not_attend'])->assertSessionHasNoErrors();
    expect($r->fresh()->status)->toBe('cancelled')->and($r->fresh()->terminal_side)->toBe('receiver');
    $r2 = sentReferral($this->clin, $this->toF);
    signedIn($this, $this->clin)->post("/referrals/{$r2->id}/cancel", [])->assertSessionHasErrors('reason');
    signedIn($this, $this->clin)->post("/referrals/{$r2->id}/cancel", ['reason' => 'referred_elsewhere'])->assertSessionHasNoErrors();
    expect($r2->fresh()->status)->toBe('cancelled');
});

it('rejects illegal moves and moves by the wrong side or role', function () {
    $r = sentReferral($this->clin, $this->toF);
    // receiving clinician cannot accept; referrer cannot accept; cannot jump to attended or completed
    $rc = user(Role::Clinician, $this->toF);
    signedIn($this, $rc)->post("/referrals/{$r->id}/accept")->assertForbidden();
    signedIn($this, $this->clin)->post("/referrals/{$r->id}/accept")->assertForbidden();
    signedIn($this, $this->coord)->post("/referrals/{$r->id}/attend")->assertSessionHasErrors('status');
    signedIn($this, $this->coord)->post("/referrals/{$r->id}/complete", ['outcome' => 'admitted', 'summary' => 'x'])->assertSessionHasErrors('status');
    expect($r->fresh()->status)->toBe('sent');
    // terminal states stay terminal
    signedIn($this, $this->coord)->post("/referrals/{$r->id}/decline", ['reason' => 'other'])->assertSessionHasNoErrors();
    signedIn($this, $this->coord)->post("/referrals/{$r->id}/accept")->assertSessionHasErrors('status');
});

it('cannot complete without an outcome and summary (the loop must close)', function () {
    $r = acceptedReferral($this->clin, $this->coord);
    signedIn($this, $this->coord)->post("/referrals/{$r->id}/schedule", ['appointment_at' => now()->addDay()->format('Y-m-d\TH:i')]);
    signedIn($this, $this->coord)->post("/referrals/{$r->id}/attend");
    signedIn($this, $this->coord)->post("/referrals/{$r->id}/complete", ['outcome' => 'admitted', 'summary' => ''])->assertSessionHasErrors('summary');
    expect($r->fresh()->status)->toBe('attended');
});

it('requires a consent record before send (consent gating)', function () {
    signedIn($this, $this->clin)->post('/referrals', wizardPayload(['consent_confirm' => null]))->assertSessionHasErrors('consent_confirm');
    signedIn($this, $this->clin)->post('/referrals', wizardPayload(['consent_basis' => null]))->assertSessionHasErrors('consent_basis');
    expect(Referral::query()->count())->toBe(0); // nothing half-created
    // a saved draft needs no consent, but cannot be sent without it
    signedIn($this, $this->clin)->post('/referrals', wizardPayload(['action' => 'draft', 'consent_basis' => null, 'consent_confirm' => null]))->assertSessionHasNoErrors();
    $r = Referral::query()->first();
    expect($r->status)->toBe('created')->and($r->sent_at)->toBeNull();
    signedIn($this, $this->clin)->post("/referrals/{$r->id}/send", [])->assertSessionHasErrors(['consent_basis', 'consent_confirm']);
    expect($r->fresh()->status)->toBe('created');
    // service-level guard too
    expect(fn () => App\Domain\ReferralService::send($this->clin, $r, ['confirmed' => false, 'basis' => 'written']))->toThrow(Illuminate\Validation\ValidationException::class);
});

it('requires a phone confirmation for stale or unverified destinations', function () {
    $stale = facility(['name' => 'Stale', 'info_verified_at' => now()->subDays(45)]);
    $never = facility(['name' => 'Never', 'info_verified_at' => null]);
    foreach ([$stale, $never] as $f) {
        signedIn($this, $this->clin)->post('/referrals', wizardPayload(['receiving_facility_id' => $f->id]))->assertSessionHasErrors('stale_confirmed');
    }
    expect(Referral::query()->count())->toBe(0);
    signedIn($this, $this->clin)->post('/referrals', wizardPayload(['receiving_facility_id' => $stale->id, 'stale_confirmed' => '1']))->assertSessionHasNoErrors();
    expect(Referral::query()->first()->stale_confirmed)->toBeTrue();
});

it('validates input and enforces the configurable form schema', function () {
    $c = signedIn($this, $this->clin);
    $c->post('/referrals', wizardPayload(['priority' => 'whenever']))->assertSessionHasErrors('priority');
    $c->post('/referrals', wizardPayload(['reason_text' => str_repeat('x', 241)]))->assertSessionHasErrors('reason_text');
    $c->post('/referrals', wizardPayload(['reason_code' => 'nope']))->assertSessionHasErrors('reason_code');
    $c->post('/referrals', wizardPayload(['receiving_facility_id' => $this->fromF->id]))->assertSessionHasErrors(); // cannot refer to self
    $c->post('/referrals', wizardPayload(['form' => ['allergies' => 'maybe']]))->assertSessionHasErrors('form.allergies');
    $c->post('/referrals', wizardPayload(['synthetic_confirm' => null]))->assertSessionHasErrors('synthetic_confirm');
    expect(Referral::query()->count())->toBe(0);
});

it('flags a second open referral for the same patient and destination', function () {
    $c = signedIn($this, $this->clin);
    $c->post('/referrals', wizardPayload())->assertSessionHasNoErrors();
    $p = App\Models\Patient::query()->withoutGlobalScopes()->first();
    $c->post('/referrals', wizardPayload(['patient_mode' => 'existing', 'existing_patient_id' => $p->id]))->assertSessionHasErrors('override_reason');
    $c->post('/referrals', wizardPayload(['patient_mode' => 'existing', 'existing_patient_id' => $p->id, 'override_reason' => 'New problem']))->assertSessionHasNoErrors();
    expect(Referral::query()->count())->toBe(2);
});

it('two-way messages work and an info request is tracked until the referrer replies', function () {
    $r = sentReferral($this->clin, $this->toF);
    signedIn($this, $this->coord)->post("/referrals/{$r->id}/messages", ['body' => 'Please send weight', 'kind' => 'info_request'])->assertSessionHasNoErrors();
    expect($r->fresh()->info_requested_at)->not->toBeNull()->and($r->fresh()->info_replied_at)->toBeNull();
    signedIn($this, $this->clin)->get('/')->assertSee('More information was requested');
    signedIn($this, $this->clin)->post("/referrals/{$r->id}/messages", ['body' => '12 kg'])->assertSessionHasNoErrors();
    expect($r->fresh()->info_replied_at)->not->toBeNull();
    signedIn($this, $this->clin)->get("/referrals/{$r->id}?tab=messages")->assertSee('Please send weight')->assertSee('12 kg');
    // referrer cannot raise an info request
    signedIn($this, $this->clin)->post("/referrals/{$r->id}/messages", ['body' => 'x', 'kind' => 'info_request'])->assertForbidden();
    // empty / oversize messages rejected
    signedIn($this, $this->coord)->post("/referrals/{$r->id}/messages", ['body' => ''])->assertSessionHasErrors('body');
    signedIn($this, $this->coord)->post("/referrals/{$r->id}/messages", ['body' => str_repeat('a', 2001)])->assertSessionHasErrors('body');
});

it('withdrawing consent cancels the referral and stops sharing', function () {
    $r = acceptedReferral($this->clin, $this->coord);
    signedIn($this, $this->clin)->post("/referrals/{$r->id}/withdraw-consent")->assertSessionHasNoErrors();
    $r->refresh();
    expect($r->status)->toBe('cancelled')->and($r->terminal_reason)->toBe('consent_withdrawn');
    signedIn($this, $this->coord)->get("/referrals/{$r->id}")->assertOk()->assertSee('Sharing for this referral has stopped')->assertDontSee('Synthetic clinical note')->assertDontSee('Synthetic reason text');
});
