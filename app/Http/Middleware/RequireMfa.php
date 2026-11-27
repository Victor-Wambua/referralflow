<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** A signed-in user is not usable until the TOTP step is done and the account is active. */
class RequireMfa
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        if (! $u) {
            return redirect()->route('login');
        }
        if (! $u->isActive()) {
            auth()->logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => __('This account is not active.')]);
        }
        if (! $request->session()->get('mfa_ok')) {
            return redirect()->route('mfa');
        }
        if ($u->must_change_password && ! $request->routeIs('password.*', 'logout')) {
            return redirect()->route('password.edit');
        }

        return $next($request);
    }
}
