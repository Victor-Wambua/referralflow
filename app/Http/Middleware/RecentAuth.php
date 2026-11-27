<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Sensitive actions need a fresh password + TOTP re-authentication (within reauth_minutes). */
class RecentAuth
{
    public function handle(Request $request, Closure $next)
    {
        $at = (int) $request->session()->get('reauth_at', 0);
        if ((time() - $at) > (int) config('referralflow.session.reauth_minutes') * 60) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Re-authentication required'], 423);
            }
            $request->session()->put('reauth_return', $request->isMethod('GET') ? $request->fullUrl() : url()->previous());

            return redirect()->route('reauth');
        }

        return $next($request);
    }
}
