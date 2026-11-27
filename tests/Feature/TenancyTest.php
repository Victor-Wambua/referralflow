<?php

use App\Domain\ReferralService;
use App\Enums\Role;
use App\Models\Message;
use App\Models\Patient;
use App\Models\Referral;
use App\Security\ReferralAccess;
use App\Security\TenantContext;

beforeEach(function () {
    $this->A = facility(['name' => 'Alpha HC']);
    $this->B = facility(['name' => 'Beta Hospital']);
    $this->C = facility(['name' => 'Gamma Clinic']);
    $this->u = [];
    foreach (['A' => $this->A, 'B' => $this->B, 'C' => $this->C] as $k => $f) {
        $this->u[$k.'_clin'] = user(Role::Clinician, $f);
        $this->u[$k.'_coord'] = user(Role::Coordinator, $f);
        $this->u[$k.'_admin'] = user(Role::FacilityAdmin, $f);
    }
    $this->u['analyst'] = user(Role::NetworkAnalyst);
    $this->u['platform'] = user(Role::PlatformAdmin);

    $a = $this->u['A_clin'];
    $b = $this->u['B_coord'];
    $p = fn () => patientFor($a, ['full_name' => 'Zebedee Uniquename']);
    $refs = [];
    $refs['draft'] = draftReferral($a, $this->B, ['patient' => $p()]);
    $refs['sent'] = sentReferral($a, $this->B, ['patient' => $p()]);
    $refs['received'] = sentReferral($a, $this->B, ['patient' => $p()]);
    test()->be($b);
    ReferralService::markReceived($b, $refs['received']);
    $refs['accepted'] = acceptedReferral($a, $b, ['patient' => $p()]);
    $refs['scheduled'] = acceptedReferral($a, $b, ['patient' => $p()]);
    ReferralService::schedule($b, $refs['scheduled'], now()->addDay());
    $refs['declined'] = sentReferral($a, $this->B, ['patient' => $p()]);
    ReferralService::decline($b, $refs['declined'], 'no_capacity');
    $refs['cancelled_early'] = sentReferral($a, $this->B, ['patient' => $p()]);
    ReferralService::cancel($a, $refs['cancelled_early'], 'created_in_error');
    $refs['cancelled_late'] = acceptedReferral($a, $b, ['patient' => $p()]);
    ReferralService::cancel($a, $refs['cancelled_late'], 'patient_declined');
    $refs['withdrawn'] = acceptedReferral($a, $b, ['patient' => $p()]);
    ReferralService::withdrawConsent($a, $refs['withdrawn']);
    $this->refs = $refs;
});

function expectedLevel(string $who, string $ref): string
{
    $side = str_starts_with($who, 'A_') ? 'A' : (str_starts_with($who, 'B_') ? 'B' : null);
    $clinical = str_ends_with($who, '_clin') || str_ends_with($who, '_coord');
    if (! $clinical || $side === null || $side === 'C') {
        return 'none';
    }
    if ($side === 'A') {
        return 'full';
    }

    return match ($ref) {
        'draft' => 'none',
        'sent', 'received', 'declined', 'cancelled_early' => 'minimal',
        'accepted', 'scheduled', 'cancelled_late' => 'full',
        'withdrawn' => 'tombstone',
    };
}

it('computes the exact access level for every user x referral state', function () {
    $n = 0;
    foreach ($this->u as $who => $user) {
        foreach ($this->refs as $name => $ref) {
            expect(ReferralAccess::level($user, $ref->fresh()))->toBe(expectedLevel($who, $name), "$who on $name");
            $n++;
        }
    }
    expect($n)->toBe(11 * 9 /* 11 users x 9 referrals */);
});

it('keeps the list query and the per-record level consistent (single query layer)', function () {
    foreach ($this->u as $who => $user) {
        $this->be($user);
        $visible = Referral::query()->pluck('id')->all();
        foreach ($this->refs as $name => $ref) {
            $level = ReferralAccess::level($user, $ref->fresh());
            expect(in_array($ref->id, $visible, true))->toBe($level !== 'none', "$who list vs level on $name");
        }
    }
});

it('returns nothing without an authenticated user (default deny) and everything only in explicit system context', function () {
    auth()->logout();
    expect(Referral::query()->count())->toBe(0)->and(Patient::query()->count())->toBe(0)->and(Message::query()->count())->toBe(0);
    expect(TenantContext::asSystem(fn () => Referral::query()->count()))->toBe(9);
});

it('shows each user only what the access level allows over HTTP', function () {
    foreach ($this->u as $who => $user) {
        foreach ($this->refs as $name => $ref) {
            $level = expectedLevel($who, $name);
            $res = signedIn($this, $user)->get("/referrals/{$ref->id}");
            if (! str_ends_with($who, '_clin') && ! str_ends_with($who, '_coord')) {
                $res->assertForbidden();

                continue;
            }
            if ($level === 'none') {
                $res->assertForbidden()->assertSee('You do not have access to this referral')->assertDontSee('Synthetic reason text');

                continue;
            }
            $res->assertOk();
            $res->assertDontSee('Zebedee'); // identity is masked by default for everybody
            match ($level) {
                'full' => $res->assertSee('Synthetic reason text')->assertSee('Synthetic clinical note'),
                'minimal' => $res->assertSee('Synthetic reason text')->assertDontSee('Synthetic clinical note')->assertDontSee('Synthetic treatment'),
                'tombstone' => $res->assertDontSee('Synthetic reason text')->assertDontSee('Synthetic clinical note'),
            };
        }
    }
});

it('does not let a receiving facility see a referral before it is sent, nor an outsider ever', function () {
    $draft = $this->refs['draft'];
    foreach (['B_clin', 'B_coord', 'C_clin', 'C_coord'] as $who) {
        $this->be($this->u[$who]);
        expect(Referral::query()->whereKey($draft->id)->exists())->toBeFalse();
        expect(ReferralAccess::locate($this->u[$who], $draft->id))->toBe([null, 'none']);
    }
    foreach ($this->refs as $ref) {
        foreach (['C_clin', 'C_coord'] as $who) {
            expect(ReferralAccess::level($this->u[$who], $ref->fresh()))->toBe('none');
        }
    }
});

it('hides patients of other facilities and the existence of ids is not revealed', function () {
    $this->be($this->u['B_clin']);
    expect(Patient::query()->count())->toBe(0);
    $this->be($this->u['A_clin']);
    expect(Patient::query()->count())->toBeGreaterThan(0);
    $fake = str_repeat('0', 26);
    $real = signedIn($this, $this->u['C_clin'])->get("/referrals/{$this->refs['sent']->id}");
    $none = signedIn($this, $this->u['C_clin'])->get("/referrals/$fake");
    expect($real->status())->toBe($none->status())->and(strlen($real->getContent()) - strlen($none->getContent()))->toBeLessThan(120);
});

it('prevents cross-facility writes', function () {
    $sent = $this->refs['sent'];
    signedIn($this, $this->u['C_coord'])->post("/referrals/{$sent->id}/accept")->assertNotFound();
    signedIn($this, $this->u['C_clin'])->post("/referrals/{$sent->id}/messages", ['body' => 'hi'])->assertNotFound();
    signedIn($this, $this->u['C_clin'])->post("/referrals/{$sent->id}/cancel", ['reason' => 'other'])->assertNotFound();
    expect($sent->fresh()->status)->toBe('sent');
});

it('lets the network analyst and platform admin see aggregates only and no referral identifiers', function () {
    foreach (['analyst', 'platform'] as $k) {
        signedIn($this, $this->u[$k])->get('/dashboard')->assertOk()->assertDontSee('Zebedee')->assertDontSee('Synthetic reason text');
        foreach ($this->refs as $ref) {
            signedIn($this, $this->u[$k])->get("/referrals/{$ref->id}")->assertForbidden();
            signedIn($this, $this->u[$k])->get("/api/v1/referrals/{$ref->id}/fhir")->assertForbidden();
        }
        signedIn($this, $this->u[$k])->get('/referrals')->assertForbidden();
    }
});
