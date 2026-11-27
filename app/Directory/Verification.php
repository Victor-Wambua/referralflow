<?php

namespace App\Directory;

use App\Models\Setting;
use Illuminate\Support\Carbon;

final class Verification
{
    public static function dueDays(): int
    {
        return (int) Setting::get('verification.due_days', config('referralflow.verification.due_days'));
    }

    public static function staleDays(): int
    {
        return (int) Setting::get('verification.stale_days', config('referralflow.verification.stale_days'));
    }

    /** verified (fresh) | due | stale | unverified (never) */
    public static function state(?\DateTimeInterface $at, ?Carbon $now = null): string
    {
        if ($at === null) {
            return 'unverified';
        }
        $age = Carbon::instance($at)->diffInDays($now ?? now(), true);
        if ($age >= self::staleDays()) {
            return 'stale';
        }

        return $age >= self::dueDays() ? 'due' : 'verified';
    }

    public static function needsPhoneConfirm(string $state): bool
    {
        return in_array($state, ['stale', 'unverified'], true);
    }
}
