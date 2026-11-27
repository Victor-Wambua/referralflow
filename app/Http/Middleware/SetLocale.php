<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $loc = $request->user()?->locale ?? $request->session()->get('locale', 'en');
        app()->setLocale(in_array($loc, ['en', 'sw'], true) ? $loc : 'en');

        return $next($request);
    }
}
