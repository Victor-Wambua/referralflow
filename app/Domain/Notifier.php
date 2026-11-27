<?php

namespace App\Domain;

use App\Enums\Role;
use App\Mail\NeutralNoticeMail;
use App\Models\Referral;
use App\Models\User;
use App\Models\UserNotice;
use Illuminate\Support\Facades\Mail;

/** Notifications carry NO patient data and no clinical words: a kind, an opaque link, nothing else. */
final class Notifier
{
    public const TEXT = [
        'referral_received' => 'A referral is waiting for your response',
        'referral_overdue' => 'A referral is waiting longer than expected',
        'referral_escalated' => 'A referral still needs a response',
        'referral_accepted' => 'A referral you sent was accepted',
        'referral_declined' => 'A referral you sent needs your attention',
        'referral_scheduled' => 'An appointment was set for a referral',
        'referral_attended' => 'A referral patient has attended',
        'referral_completed' => 'A referral has a summary back',
        'referral_cancelled' => 'A referral was cancelled',
        'info_requested' => 'More information was requested on a referral',
        'message_received' => 'A new message on a referral',
        'consent_withdrawn' => 'A referral was stopped',
        'break_glass' => 'Emergency access was used and needs review',
    ];

    /** @param list<Role> $roles */
    public static function toFacility(int $facilityId, string $kind, ?Referral $referral, array $roles = [Role::Coordinator, Role::Clinician]): void
    {
        $users = User::query()->where('facility_id', $facilityId)->where('status', 'active')
            ->whereIn('role', array_map(fn ($r) => $r->value, $roles))->get();
        foreach ($users as $u) {
            self::toUser($u, $kind, $referral);
        }
    }

    public static function toUser(User $u, string $kind, ?Referral $referral): void
    {
        UserNotice::query()->create([
            'user_id' => $u->id,
            'kind' => $kind,
            'referral_id' => $referral?->id,
            'text' => self::TEXT[$kind] ?? 'You have an update',
        ]);
        try {
            Mail::to($u->email)->send(new NeutralNoticeMail($referral ? url('/referrals/'.$referral->id) : url('/notifications')));
        } catch (\Throwable) {
            // Mail failure must never break the clinical workflow; the in-app notice exists.
        }
    }
}
