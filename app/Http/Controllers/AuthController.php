<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Security\AuditLog;
use App\Security\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if ($request->user() && $request->session()->get('mfa_ok')) {
            return redirect('/');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190'], 'password' => ['required', 'string', 'max:200']]);
        $user = User::query()->where('email', strtolower($data['email']))->first();
        $generic = ['email' => __('Sign-in failed. Check your details, or the account may be locked.')];

        // Constant-ish work whether or not the account exists.
        $ok = $user ? Hash::check($data['password'], $user->password) : (bool) Hash::check($data['password'], Hash::make('x'));
        if (! $user || ! $user->isActive() || ($user->locked_until && $user->locked_until->isFuture())) {
            AuditLog::record('auth.login_denied', 'user', $user?->id);

            return back()->withErrors($generic)->onlyInput('email');
        }
        if (! $ok) {
            $this->fail($user);

            return back()->withErrors($generic)->onlyInput('email');
        }
        $user->forceFill(['failed_logins' => 0])->save();
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(['mfa_ok' => false, 'last_activity' => time(), 'session_started' => time()]);

        return redirect()->route('mfa');
    }

    public function showMfa(Request $request)
    {
        $user = $request->user();
        if ($request->session()->get('mfa_ok')) {
            return redirect('/');
        }
        if (! $user->mfa_enabled_at) {
            $secret = $request->session()->get('mfa_pending_secret') ?? Totp::generateSecret();
            $request->session()->put('mfa_pending_secret', $secret);

            return view('auth.mfa-enrol', ['secret' => $secret, 'uri' => Totp::uri($secret, $user->email), 'recovery' => session('recovery_codes')]);
        }

        return view('auth.mfa');
    }

    public function verifyMfa(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:12']]);
        $user = $request->user();
        if ($user->locked_until && $user->locked_until->isFuture()) {
            return back()->withErrors(['code' => __('Too many attempts. Try again later.')]);
        }
        if (! $user->mfa_enabled_at) {
            $secret = (string) $request->session()->get('mfa_pending_secret');
            $step = $secret ? Totp::verify($secret, $request->input('code')) : null;
            if ($step === null) {
                $this->fail($user);

                return back()->withErrors(['code' => __('That code is not right. Check the time on your phone and try again.')]);
            }
            $codes = collect(range(1, 8))->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))->all();
            $user->forceFill([
                'mfa_secret' => $secret, 'mfa_enabled_at' => now(), 'mfa_last_step' => $step,
                'mfa_recovery_codes' => array_map(fn ($c) => Hash::make($c), $codes),
            ])->save();
            $request->session()->forget('mfa_pending_secret');
            $this->complete($request, $user, 'auth.mfa_enrolled');

            return redirect('/')->with('recovery_codes', $codes);
        }
        $step = Totp::verify($user->mfa_secret, $request->input('code'), $user->mfa_last_step);
        if ($step === null) {
            $this->fail($user);

            return back()->withErrors(['code' => __('That code is not right. Check the time on your phone and try again.')]);
        }
        $user->forceFill(['mfa_last_step' => $step])->save();
        $this->complete($request, $user, 'auth.login');

        return redirect()->intended('/');
    }

    public function recovery(Request $request)
    {
        $request->validate(['recovery_code' => ['required', 'string', 'max:20']]);
        $user = $request->user();
        $codes = $user->mfa_recovery_codes ?? [];
        foreach ($codes as $i => $hash) {
            if (Hash::check(strtolower(trim($request->input('recovery_code'))), $hash)) {
                unset($codes[$i]);
                $user->forceFill(['mfa_recovery_codes' => array_values($codes)])->save();
                $this->complete($request, $user, 'auth.login_recovery');

                return redirect('/');
            }
        }
        $this->fail($user);

        return back()->withErrors(['recovery_code' => __('That recovery code is not valid.')]);
    }

    public function logout(Request $request)
    {
        if ($request->user()) {
            AuditLog::record('auth.logout', 'user', $request->user()->id);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('notice', 'signed_out');
    }

    public function keepalive()
    {
        return response()->noContent();
    }

    public function showReauth()
    {
        return view('auth.reauth');
    }

    public function reauth(Request $request)
    {
        $request->validate(['password' => ['required'], 'code' => ['required', 'string', 'max:12']]);
        $user = $request->user();
        $step = $user->mfa_secret ? Totp::verify($user->mfa_secret, $request->input('code'), $user->mfa_last_step) : null;
        if (! Hash::check($request->input('password'), $user->password) || $step === null) {
            $this->fail($user);
            AuditLog::record('auth.reauth_failed', 'user', $user->id);

            return back()->withErrors(['password' => __('Password or code is not right.')]);
        }
        $user->forceFill(['mfa_last_step' => $step, 'failed_logins' => 0])->save();
        $request->session()->put('reauth_at', time());
        AuditLog::record('auth.reauth', 'user', $user->id);

        return redirect($request->session()->pull('reauth_return', '/'));
    }

    public function editPassword()
    {
        return view('auth.password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers(), 'different:current_password'],
        ]);
        $user = $request->user();
        if (! Hash::check($request->input('current_password'), $user->password)) {
            return back()->withErrors(['current_password' => __('Current password is not right.')]);
        }
        $user->forceFill(['password' => $request->input('password'), 'must_change_password' => false])->save();
        AuditLog::record('auth.password_changed', 'user', $user->id);
        $request->session()->regenerate();

        return redirect('/')->with('status', __('Password changed.'));
    }

    private function complete(Request $request, User $user, string $audit): void
    {
        $request->session()->regenerate();
        $request->session()->put(['mfa_ok' => true, 'reauth_at' => time(), 'last_activity' => time()]);
        $user->forceFill(['last_login_at' => now(), 'failed_logins' => 0])->save();
        AuditLog::record($audit, 'user', $user->id);
    }

    private function fail(User $user): void
    {
        $max = (int) config('referralflow.lockout.max_attempts');
        $n = $user->failed_logins + 1;
        $user->forceFill(['failed_logins' => $n] + ($n >= $max ? ['locked_until' => now()->addMinutes((int) config('referralflow.lockout.minutes')), 'failed_logins' => 0] : []))->save();
        AuditLog::record('auth.failed', 'user', $user->id, ['count' => $n]);
    }
}
