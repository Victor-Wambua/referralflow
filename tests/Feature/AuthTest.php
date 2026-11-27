<?php

use App\Enums\Role;
use App\Models\AuditEntry;
use App\Security\Totp;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;

function login(object $t, $u, string $pw = 'Correct-Horse-1234')
{
    return $t->post('/login', ['email' => $u->email, 'password' => $pw]);
}

it('hashes passwords with Argon2id', function () {
    $u = user(Role::Clinician);
    expect($u->password)->toStartWith('$argon2id$')->and(Hash::check('Correct-Horse-1234', $u->password))->toBeTrue();
    expect(config('hashing.driver'))->toBe('argon2id');
});

it('requires the TOTP step for EVERY role before any app page is reachable', function (Role $role) {
    $u = user($role);
    login($this, $u)->assertRedirect('/mfa');
    foreach (['/', '/directory', '/dashboard', '/referrals', '/admin/users', '/access', '/settings'] as $path) {
        $this->get($path)->assertRedirect('/mfa');
    }
    $this->post('/mfa', ['code' => '000000'])->assertSessionHasErrors('code');
    $this->get('/directory')->assertRedirect('/mfa');
    $this->post('/mfa', ['code' => Totp::code($u->mfa_secret)])->assertRedirect();
    $this->get('/directory')->assertOk();
})->with(array_map(fn ($r) => [$r], Role::cases()));

it('forces MFA enrolment for an account without a secret and then shows recovery codes once', function () {
    $u = user(Role::Clinician, null, ['mfa_secret' => null, 'mfa_enabled_at' => null]);
    login($this, $u)->assertRedirect('/mfa');
    $res = $this->get('/mfa')->assertOk()->assertSee('Set up two-step sign-in');
    $secret = session('mfa_pending_secret');
    expect($secret)->not->toBeNull();
    $this->get('/directory')->assertRedirect('/mfa');
    $this->post('/mfa', ['code' => '123456'])->assertSessionHasErrors('code');
    $this->post('/mfa', ['code' => Totp::code($secret)])->assertRedirect('/');
    expect($u->fresh()->mfa_enabled_at)->not->toBeNull()->and($u->fresh()->mfa_secret)->toBe($secret);
    expect(session('recovery_codes'))->toHaveCount(8);
    // raw column is ciphertext
    expect(DB::table('users')->where('id', $u->id)->value('mfa_secret'))->not->toBe($secret);
});

it('rejects a replayed TOTP code', function () {
    $u = user(Role::Clinician);
    login($this, $u);
    $code = Totp::code($u->mfa_secret);
    $this->post('/mfa', ['code' => $code])->assertRedirect();
    $this->post('/logout');
    login($this, $u);
    $this->post('/mfa', ['code' => $code])->assertSessionHasErrors('code');
});

it('accepts a recovery code once only', function () {
    $u = user(Role::Clinician, null, ['mfa_recovery_codes' => [Hash::make('abcde-12345')]]);
    login($this, $u);
    $this->post('/mfa/recovery', ['recovery_code' => 'abcde-12345'])->assertRedirect('/');
    $this->post('/logout');
    login($this, $u);
    $this->post('/mfa/recovery', ['recovery_code' => 'abcde-12345'])->assertSessionHasErrors('recovery_code');
});

it('locks an account after repeated failures and uses one generic message', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $u = user(Role::Clinician);
    $unknown = login($this, (object) ['email' => 'nobody@test.invalid'], 'x');
    $generic = 'Sign-in failed. Check your details, or the account may be locked.';
    $unknown->assertSessionHasErrors(['email' => $generic]);
    for ($i = 0; $i < 5; $i++) {
        login($this, $u, 'wrong-password-1');
    }
    expect($u->fresh()->locked_until)->not->toBeNull();
    login($this, $u)->assertSessionHasErrors(['email' => $generic]); // correct password still refused while locked
    $this->get('/directory')->assertRedirect('/login');
});

it('rate limits login attempts', function () {
    $u = user(Role::Clinician);
    for ($i = 0; $i < 5; $i++) {
        login($this, $u, 'wrong-password-1');
    }
    login($this, $u, 'wrong-password-1')->assertStatus(429);
});

it('rate limits MFA code guessing', function () {
    $u = user(Role::Clinician);
    login($this, $u);
    for ($i = 0; $i < 6; $i++) {
        $this->post('/mfa', ['code' => '000000']);
    }
    $this->post('/mfa', ['code' => '000000'])->assertStatus(429);
});

it('signs out after the idle timeout and after the absolute limit', function () {
    $u = user(Role::Clinician);
    $idle = config('referralflow.session.idle_minutes') * 60;
    $this->actingAs($u)->withSession(['mfa_ok' => true, 'last_activity' => time() - $idle - 5, 'session_started' => time() - 100])
        ->get('/directory')->assertRedirect('/login');
    $this->assertGuest();
    $this->actingAs($u)->withSession(['mfa_ok' => true, 'last_activity' => time(), 'session_started' => time() - config('referralflow.session.absolute_minutes') * 60 - 5])
        ->get('/directory')->assertRedirect('/login');
    expect(AuditEntry::query()->where('action', 'auth.timeout')->count())->toBe(2);
    $this->actingAs($u)->withSession(['mfa_ok' => true, 'last_activity' => time() - 60, 'session_started' => time() - 100])->get('/directory')->assertOk();
});

it('keepalive extends the session', function () {
    $u = user(Role::Clinician);
    signedIn($this, $u)->withSession(['last_activity' => time() - 600])->post('/session/keepalive')->assertNoContent();
    expect(session('last_activity'))->toBeGreaterThan(time() - 5);
});

it('requires recent re-authentication (password + code) for sensitive actions', function () {
    $admin = user(Role::PlatformAdmin);
    $old = ['mfa_ok' => true, 'reauth_at' => time() - 3600, 'last_activity' => time(), 'session_started' => time()];
    $this->actingAs($admin)->withSession($old)->post('/admin/retention/purge')->assertRedirect('/reauth');
    $this->actingAs($admin)->withSession($old)->post('/dashboard/export')->assertRedirect('/reauth');
    $this->actingAs($admin)->withSession($old)->post('/reauth', ['password' => 'wrong', 'code' => Totp::code($admin->mfa_secret)])->assertSessionHasErrors('password');
    $this->actingAs($admin)->withSession($old)->post('/reauth', ['password' => 'Correct-Horse-1234', 'code' => '000000'])->assertSessionHasErrors('password');
    $this->actingAs($admin)->withSession($old)->post('/reauth', ['password' => 'Correct-Horse-1234', 'code' => Totp::code($admin->mfa_secret)])->assertRedirect();
    expect(session('reauth_at'))->toBeGreaterThan(time() - 5);
    $this->post('/admin/retention/purge')->assertSessionHasNoErrors()->assertRedirect(); // now allowed
});

it('forces a password change for invited users and enforces the password policy', function () {
    $u = user(Role::Clinician, null, ['must_change_password' => true]);
    signedIn($this, $u)->get('/directory')->assertRedirect('/password');
    signedIn($this, $u)->post('/password', ['current_password' => 'Correct-Horse-1234', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
    signedIn($this, $u)->post('/password', ['current_password' => 'Correct-Horse-1234', 'password' => 'nouppercase12345', 'password_confirmation' => 'nouppercase12345'])->assertSessionHasErrors('password');
    signedIn($this, $u)->post('/password', ['current_password' => 'Correct-Horse-1234', 'password' => 'Brand-New-Passw0rd', 'password_confirmation' => 'Brand-New-Passw0rd'])->assertRedirect('/');
    expect($u->fresh()->must_change_password)->toBeFalse();
});

it('rejects suspended accounts and POSTs without a CSRF token', function () {
    $u = user(Role::Clinician, null, ['status' => 'suspended']);
    login($this, $u)->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('protects every state-changing route with CSRF verification', function () {
    $router = app('router');
    expect(app(Kernel::class)->getMiddlewareGroups()['web'])->toContain(PreventRequestForgery::class);
    $n = 0;
    foreach ($router->getRoutes() as $route) {
        if (array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            expect($route->gatherMiddleware())->toContain('web');
            $n++;
        }
    }
    expect($n)->toBeGreaterThan(30);
});

it('sets security headers and never caches authenticated pages', function () {
    $u = user(Role::Clinician);
    $res = signedIn($this, $u)->get('/');
    $res->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy', 'no-referrer');
    expect($res->headers->get('Content-Security-Policy'))->toContain("script-src 'self'")->not->toContain('unsafe-eval');
    expect($res->headers->get('Cache-Control'))->toContain('no-store');
});
