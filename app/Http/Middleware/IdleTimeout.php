<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Short idle timeout plus an absolute session limit. */
class IdleTimeout
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            $now = time();
            $s = $request->session();
            $idle = (int) config('referralflow.session.idle_minutes') * 60;
            $abs = (int) config('referralflow.session.absolute_minutes') * 60;
            $last = (int) $s->get('last_activity', $now);
            $started = (int) $s->get('session_started', $now);
            if (($now - $last) > $idle || ($now - $started) > $abs) {
                \App\Security\AuditLog::record('auth.timeout', 'user', $request->user()->id);
                auth()->logout();
                $s->invalidate();
                $s->regenerateToken();

                return $request->expectsJson()
                    ? response()->json(['message' => 'Session expired'], 401)
                    : redirect()->route('login')->with('notice', 'timeout');
            }
            $s->put('last_activity', $now);
            $s->put('session_started', $started);
        }

        return $next($request);
    }
}
