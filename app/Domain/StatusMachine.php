<?php

namespace App\Domain;

use App\Enums\Role;
use App\Models\Referral;
use App\Models\User;
use App\Security\ReferralAccess;

/**
 * The nine statuses and the only legal moves between them.
 * Each move names the side (referrer|receiver) and roles that may perform it.
 */
final class StatusMachine
{
    private const CLIN = [Role::Clinician, Role::Coordinator];

    private const COORD = [Role::Coordinator];

    /** @return array<string, array<string, array{side:string|array, roles:array}>> */
    private static function table(): array
    {
        return [
            'created' => [
                'sent' => ['side' => 'referrer', 'roles' => self::CLIN],
                'cancelled' => ['side' => 'referrer', 'roles' => self::CLIN],
            ],
            'sent' => [
                'received' => ['side' => 'receiver', 'roles' => self::CLIN],
                'declined' => ['side' => 'receiver', 'roles' => self::COORD],
                'cancelled' => ['side' => 'referrer', 'roles' => self::CLIN],
            ],
            'received' => [
                'accepted' => ['side' => 'receiver', 'roles' => self::COORD],
                'declined' => ['side' => 'receiver', 'roles' => self::COORD],
                'cancelled' => ['side' => 'referrer', 'roles' => self::CLIN],
            ],
            'accepted' => [
                'scheduled' => ['side' => 'receiver', 'roles' => self::COORD],
                'cancelled' => ['side' => ['referrer', 'receiver'], 'roles' => self::CLIN],
            ],
            'scheduled' => [
                'attended' => ['side' => 'receiver', 'roles' => self::CLIN],
                'cancelled' => ['side' => ['referrer', 'receiver'], 'roles' => self::CLIN],
            ],
            'attended' => [
                'completed' => ['side' => 'receiver', 'roles' => self::CLIN],
            ],
            'completed' => [],
            'declined' => [],
            'cancelled' => [],
        ];
    }

    public static function isLegal(string $from, string $to): bool
    {
        return isset(self::table()[$from][$to]);
    }

    /** @return list<string> */
    public static function targets(string $from): array
    {
        return array_keys(self::table()[$from] ?? []);
    }

    public static function requiresReason(string $to): bool
    {
        return in_array($to, ['declined', 'cancelled'], true);
    }

    public static function may(User $u, Referral $r, string $to): bool
    {
        $rule = self::table()[$r->status][$to] ?? null;
        if (! $rule) {
            return false;
        }
        $level = ReferralAccess::level($u, $r);
        if ($level === ReferralAccess::NONE || $level === ReferralAccess::BREAKGLASS || $level === ReferralAccess::TOMBSTONE) {
            return false;
        }
        $side = ReferralAccess::side($u, $r);

        return in_array($side, (array) $rule['side'], true) && in_array($u->role, $rule['roles'], true);
    }

    /** Transitions this user may take right now. */
    public static function available(User $u, Referral $r): array
    {
        return array_values(array_filter(self::targets($r->status), fn ($to) => self::may($u, $r, $to)));
    }
}
