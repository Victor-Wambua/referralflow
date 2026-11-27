<?php

namespace App\Security;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Who is asking? Models read this to scope queries. Default is deny: with no
 * authenticated user and no explicit system context, scoped models return nothing.
 */
final class TenantContext
{
    private static int $system = 0;

    public static function user(): ?User
    {
        $u = Auth::guard('web')->user();

        return $u instanceof User ? $u : null;
    }

    public static function isSystem(): bool
    {
        return self::$system > 0;
    }

    /** Run trusted internal work (seeding, scheduled jobs, aggregate metrics). */
    public static function asSystem(callable $fn): mixed
    {
        self::$system++;
        try {
            return $fn();
        } finally {
            self::$system--;
        }
    }
}
