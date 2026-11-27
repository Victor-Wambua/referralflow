<?php

use App\Enums\Role;

const ALL = ['clinician', 'coordinator', 'facility_admin', 'network_analyst', 'platform_admin'];

/** A fresh SENT referral to $this->fac, so mutating cells never disturb each other. */
function fresh()
{
    $t = test();

    return sentReferral($t->sender, $t->fac);
}

/** [family, method, path (string or closure), allowed roles] */
function matrix(string $fid, string $rid): array
{
    $clin = ['clinician', 'coordinator'];

    return [
        ['home', 'GET', '/', ALL],
        ['notifications', 'GET', '/notifications', ALL],
        ['settings', 'GET', '/settings', ALL],
        ['directory list', 'GET', '/directory', ALL],
        ['directory detail', 'GET', "/directory/$fid", ALL],
        ['directory snapshot api', 'GET', '/api/v1/directory', ALL],
        ['directory verify', 'POST', "/directory/$fid/verify", ['coordinator', 'facility_admin', 'platform_admin']],
        ['directory add service', 'POST', "/directory/$fid/services", ['coordinator', 'facility_admin', 'platform_admin']],
        ['directory report', 'POST', "/directory/$fid/report", ['clinician', 'coordinator', 'facility_admin']],
        ['dashboard', 'GET', '/dashboard', ['coordinator', 'facility_admin', 'network_analyst', 'platform_admin']],
        ['dashboard export', 'POST', '/dashboard/export', ['coordinator', 'facility_admin', 'network_analyst', 'platform_admin']],
        ['referral list', 'GET', '/referrals', $clin],
        ['referral new', 'GET', '/referrals/new', $clin],
        ['referral create', 'POST', '/referrals', $clin],
        ['inbox', 'GET', '/inbox', $clin],
        ['referral show', 'GET', "/referrals/$rid", $clin],
        // Domain-level rules on a SENT referral viewed from the receiving facility: only a coordinator may respond;
        // identity is not shared before acceptance (reveal refused); only the referrer may upload documents.
        ['referral accept', 'POST', fn () => '/referrals/'.fresh()->id.'/accept', ['coordinator']],
        ['referral decline', 'POST', fn () => '/referrals/'.fresh()->id.'/decline?reason=other', ['coordinator']],
        ['referral message', 'POST', "/referrals/$rid/messages", $clin],
        ['referral reveal', 'POST', "/referrals/$rid/reveal", []],
        ['referral upload', 'POST', "/referrals/$rid/documents", []],
        ['referral fhir export', 'GET', "/api/v1/referrals/$rid/fhir", $clin],
        ['break-glass request', 'POST', "/referrals/$rid/break-glass", $clin],
        ['data requests (staff)', 'GET', '/data-requests', $clin],
        ['data requests (admin review)', 'GET', '/data-requests/review', ['facility_admin']],
        ['access log', 'GET', '/access', ['clinician', 'coordinator', 'facility_admin']],
        ['admin users', 'GET', '/admin/users', ['facility_admin', 'platform_admin']],
        ['admin user invite', 'POST', '/admin/users', ['facility_admin', 'platform_admin']],
        ['admin break-glass reviews', 'GET', '/admin/break-glass', ['facility_admin', 'platform_admin']],
        ['admin facilities', 'GET', '/admin/facilities', ['platform_admin']],
        ['admin facility register', 'POST', '/admin/facilities', ['platform_admin']],
        ['admin facility import', 'POST', '/admin/facilities/import', ['platform_admin']],
        ['audit explorer', 'GET', '/admin/audit', ['platform_admin']],
        ['audit export', 'POST', '/admin/audit/export', ['platform_admin']],
        ['retention', 'GET', '/admin/retention', ['platform_admin']],
        ['retention save', 'POST', '/admin/retention', ['platform_admin']],
        ['retention purge', 'POST', '/admin/retention/purge', ['platform_admin']],
        ['legal hold', 'POST', '/admin/holds', ['platform_admin']],
        ['form schemas', 'GET', '/admin/forms', ['platform_admin']],
        ['form schema create', 'POST', '/admin/forms', ['platform_admin']],
    ];
}

beforeEach(function () {
    $this->fac = facility();
    $this->users = [];
    foreach (Role::cases() as $r) {
        $this->users[$r->value] = user($r, $r->needsFacility() ? $this->fac : null);
    }
    $other = facility();
    $this->sender = user(Role::Clinician, $other);
    $this->rid = sentReferral($this->sender, $this->fac)->id;
    $this->matrix = matrix((string) $this->fac->id, $this->rid);
    app('auth')->forgetGuards();
});

it('enforces every role x endpoint family (allowed gets through, others get 403)', function () {
    $cells = 0;
    foreach ($this->matrix as [$family, $method, $path, $allowed]) {
        foreach (Role::cases() as $role) {
            $p = is_callable($path) ? $path() : $path;
            app('auth')->forgetGuards();
            $res = signedIn($this, $this->users[$role->value])->call($method, $p, str_contains($p, '?reason=') ? ['reason' => 'other'] : []);
            $label = "{$role->value} $method $p ($family)";
            if (in_array($role->value, $allowed, true)) {
                expect($res->status())->not->toBe(403, $label);
                expect((string) $res->headers->get('Location'))->not->toContain('/login', $label);
            } else {
                expect($res->status())->toBe(403, $label);
            }
            $cells++;
        }
    }
    expect($cells)->toBe(count($this->matrix) * 5);
});

it('sends signed-out visitors to the login page for every protected endpoint', function () {
    foreach ($this->matrix as [$family, $method, $path]) {
        $path = is_callable($path) ? $path() : $path;
        app('auth')->forgetGuards();
        $res = $this->call($method, $path);
        str_starts_with($path, '/api/') ? $res->assertStatus(401) : $res->assertRedirect('/login');
    }
});

it('sends signed-in but MFA-unverified sessions to the MFA step for every protected endpoint', function () {
    foreach ($this->matrix as [$family, $method, $path]) {
        $path = is_callable($path) ? $path() : $path;
        $this->actingAs($this->users['clinician'])->withSession(['mfa_ok' => false, 'last_activity' => time(), 'session_started' => time()])
            ->call($method, $path)->assertRedirect('/mfa');
    }
});

it('requires re-authentication on every sensitive POST even for allowed roles', function () {
    $sensitive = ['dashboard export', 'break-glass request', 'admin user invite', 'admin facility register', 'admin facility import', 'audit export', 'retention save', 'retention purge', 'legal hold', 'form schema create'];
    foreach ($this->matrix as [$family, $method, $path, $allowed]) {
        if (! in_array($family, $sensitive, true)) {
            continue;
        }
        $role = $allowed[0] === 'clinician' ? 'clinician' : $allowed[0];
        $path = is_callable($path) ? $path() : $path;
        $this->actingAs($this->users[$role])->withSession(['mfa_ok' => true, 'reauth_at' => time() - 7200, 'last_activity' => time(), 'session_started' => time()])
            ->call($method, $path)->assertRedirect('/reauth');
    }
});

it('keeps the public endpoints free of patient data', function () {
    $this->get('/health')->assertOk()->assertJson(['status' => 'ok', 'synthetic_data_only' => true]);
    $this->get('/offline')->assertOk();
    $this->get('/login')->assertOk();
});
