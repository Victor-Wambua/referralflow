<?php

namespace App\Providers;

use App\Domain\NullScanner;
use App\Domain\VirusScanner;
use App\Security\ProductionGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(VirusScanner::class, NullScanner::class);
    }

    public function boot(): void
    {
        if (! $this->app->runningUnitTests()) {
            ProductionGuard::assertBootable();
        }

        $by = fn (Request $r) => (Auth::id() ?? 'guest').'|'.$r->ip();
        $cfg = config('referralflow.rate_limits');
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute($cfg['login'])->by(strtolower((string) $r->input('email')).'|'.$r->ip()));
        RateLimiter::for('mfa', fn (Request $r) => Limit::perMinute($cfg['mfa'])->by($by($r)));
        RateLimiter::for('reveal', fn (Request $r) => Limit::perMinute($cfg['reveal'])->by($by($r)));
        RateLimiter::for('search', fn (Request $r) => Limit::perMinute($cfg['search'])->by($by($r)));
        RateLimiter::for('sensitive', fn (Request $r) => Limit::perMinute($cfg['sensitive'])->by($by($r)));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute($cfg['api'])->by($by($r)));
    }
}
