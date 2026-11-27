<?php

namespace App\Domain;

use App\Directory\Verification;
use App\Models\Consent;
use App\Models\CounterReferral;
use App\Models\Facility;
use App\Models\Message;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\ReferralEvent;
use App\Models\User;
use App\Security\AuditLog;
use App\Security\ReferralAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** All referral writes go through here: status machine, reasons, events, audit and notifications. */
final class ReferralService
{
    public static function create(User $u, Patient $patient, array $d): Referral
    {
        if (! $u->isClinical()) {
            throw new AuthorizationException('Role may not create referrals.');
        }
        $dest = Facility::query()->where('is_active', true)->find($d['receiving_facility_id'] ?? 0);
        if (! $dest || $dest->id === $u->facility_id) {
            throw ValidationException::withMessages(['receiving_facility_id' => 'Choose a different, active facility.']);
        }

        return DB::transaction(function () use ($u, $patient, $d, $dest) {
            $r = Referral::query()->create([
                'referring_facility_id' => $u->facility_id,
                'receiving_facility_id' => $dest->id,
                'created_by' => $u->id,
                'patient_id' => $patient->id,
                'form_schema_id' => $d['form_schema_id'] ?? null,
                'priority' => $d['priority'],
                'reason_code' => $d['reason_code'],
                'reason_text' => $d['reason_text'] ?? null,
                'include_note' => (bool) ($d['include_note'] ?? false),
                'clinical_note' => ! empty($d['include_note']) ? ($d['clinical_note'] ?? null) : null,
                'form_data' => $d['form_data'] ?? null,
                'patient_sex' => $patient->sex,
                'patient_age' => $patient->ageYears(),
                'status' => 'created',
                'is_synthetic' => true,
            ]);
            self::event($r, null, 'created', $u);
            AuditLog::record('referral.create', 'referral', $r->id, ['priority' => $r->priority]);

            return $r;
        });
    }

    public static function send(User $u, Referral $r, array $consent, ?string $overrideReason = null, bool $staleConfirmed = false): Referral
    {
        self::guard($u, $r, 'sent');
        if (empty($consent['confirmed']) || ! array_key_exists($consent['basis'] ?? '', config('referralflow.consent_bases'))) {
            throw ValidationException::withMessages(['consent' => 'A consent record is required before sending.']);
        }
        $dest = Facility::query()->findOrFail($r->receiving_facility_id);
        if (! $dest->is_active) {
            throw ValidationException::withMessages(['receiving_facility_id' => 'This facility is not active.']);
        }
        $state = $dest->verificationState();
        if (Verification::needsPhoneConfirm($state) && ! $staleConfirmed) {
            throw ValidationException::withMessages(['stale_confirmed' => 'This facility’s information is '.$state.'. Confirm by phone before sending.']);
        }
        $dup = Referral::query()->where('patient_id', $r->patient_id)->where('receiving_facility_id', $r->receiving_facility_id)
            ->where('id', '!=', $r->id)->whereNotIn('status', ['completed', 'declined', 'cancelled', 'created'])
            ->where('created_at', '>=', now()->subDays(7))->exists();
        if ($dup && ! $overrideReason) {
            throw ValidationException::withMessages(['override_reason' => 'A referral for this patient to this facility is already open. Give a reason to create another.']);
        }

        return DB::transaction(function () use ($u, $r, $consent, $overrideReason, $staleConfirmed) {
            Consent::query()->create([
                'referral_id' => $r->id,
                'patient_id' => $r->patient_id,
                'recorded_by' => $u->id,
                'shared_with_facility_id' => $r->receiving_facility_id,
                'basis' => $consent['basis'],
                'includes_clinical_note' => $r->include_note,
                'wording_version' => 'v1-draft-counsel-review-pending',
                'witness' => $consent['witness'] ?? null,
                'recorded_at' => now(),
            ]);
            $r->forceFill(['sent_at' => now(), 'stale_confirmed' => $staleConfirmed, 'override_reason' => $overrideReason])->save();
            self::move($r, 'sent', $u);
            AuditLog::record('consent.record', 'referral', $r->id, ['basis' => $consent['basis']]);
            Notifier::toFacility($r->receiving_facility_id, 'referral_received', $r);

            return $r->refresh();
        });
    }

    public static function markReceived(User $u, Referral $r): Referral
    {
        if ($r->status !== 'sent') {
            return $r;
        }
        self::move($r, 'received', $u, set: ['received_at' => now()]);

        return $r;
    }

    public static function accept(User $u, Referral $r): Referral
    {
        if ($r->status === 'sent') {
            // Check authority to accept BEFORE the implicit sent -> received step, so a refused attempt changes nothing.
            $probe = $r->replicate();
            $probe->id = $r->id;
            $probe->status = 'received';
            if (! StatusMachine::may($u, $probe, 'accepted')) {
                throw new AuthorizationException('You may not make this change.');
            }
            self::markReceived($u, $r);
        }
        self::move($r, 'accepted', $u, set: ['accepted_at' => now()]);
        Notifier::toFacility($r->referring_facility_id, 'referral_accepted', $r);

        return $r;
    }

    public static function decline(User $u, Referral $r, string $reason, ?string $note = null): Referral
    {
        self::requireReason('declined', $reason);
        self::move($r, 'declined', $u, $reason, $note, ['declined_at' => now(), 'terminal_reason' => $reason, 'terminal_note' => $note, 'terminal_side' => 'receiver']);
        Notifier::toFacility($r->referring_facility_id, 'referral_declined', $r);

        return $r;
    }

    public static function schedule(User $u, Referral $r, \DateTimeInterface $when): Referral
    {
        if ($when < now()->subMinutes(5)) {
            throw ValidationException::withMessages(['appointment_at' => 'Choose a date and time in the future.']);
        }
        if ($r->status === 'scheduled') {
            return self::reschedule($u, $r, $when);
        }
        self::move($r, 'scheduled', $u, set: ['scheduled_at' => now(), 'appointment_at' => $when]);
        Notifier::toFacility($r->referring_facility_id, 'referral_scheduled', $r);

        return $r;
    }

    public static function reschedule(User $u, Referral $r, \DateTimeInterface $when): Referral
    {
        if ($r->status !== 'scheduled' || ! StatusMachine::may($u, $r, 'attended')) {
            throw new AuthorizationException('Cannot reschedule.');
        }
        DB::transaction(function () use ($u, $r, $when) {
            $r->forceFill(['appointment_at' => $when])->save();
            self::event($r, 'scheduled', 'scheduled', $u, 'rescheduled');
            AuditLog::record('referral.reschedule', 'referral', $r->id);
        });
        Notifier::toFacility($r->referring_facility_id, 'referral_scheduled', $r);

        return $r;
    }

    public static function markAttended(User $u, Referral $r): Referral
    {
        self::move($r, 'attended', $u, set: ['attended_at' => now()]);
        Notifier::toFacility($r->referring_facility_id, 'referral_attended', $r);

        return $r;
    }

    /** Completion is only possible with a counter-referral (feedback) summary: the loop is closed. */
    public static function complete(User $u, Referral $r, string $outcome, string $summary, ?string $followUp = null): Referral
    {
        if (! array_key_exists($outcome, config('referralflow.outcomes')) || trim($summary) === '') {
            throw ValidationException::withMessages(['summary' => 'An outcome and a summary are required to complete a referral.']);
        }
        self::guard($u, $r, 'completed');
        DB::transaction(function () use ($u, $r, $outcome, $summary, $followUp) {
            CounterReferral::query()->create(['referral_id' => $r->id, 'author_id' => $u->id, 'outcome' => $outcome, 'summary' => $summary, 'follow_up' => $followUp]);
            self::move($r, 'completed', $u, set: ['completed_at' => now()]);
            AuditLog::record('counter_referral.create', 'referral', $r->id, ['outcome' => $outcome]);
        });
        Notifier::toFacility($r->referring_facility_id, 'referral_completed', $r);

        return $r;
    }

    public static function cancel(User $u, Referral $r, string $reason, ?string $note = null): Referral
    {
        self::requireReason('cancelled', $reason);
        $side = ReferralAccess::side($u, $r);
        self::move($r, 'cancelled', $u, $reason, $note, ['cancelled_at' => now(), 'terminal_reason' => $reason, 'terminal_note' => $note, 'terminal_side' => $side]);
        $other = $side === 'referrer' ? $r->receiving_facility_id : $r->referring_facility_id;
        if ($r->sent_at) {
            Notifier::toFacility($other, 'referral_cancelled', $r);
        }

        return $r;
    }

    public static function withdrawConsent(User $u, Referral $r): Referral
    {
        if (ReferralAccess::side($u, $r) !== 'referrer' || ! $u->isClinical()) {
            throw new AuthorizationException('Only the referring facility can record a withdrawal.');
        }
        $consent = $r->consent()->first();
        if (! $consent || $consent->withdrawn_at) {
            throw ValidationException::withMessages(['consent' => 'No active consent to withdraw.']);
        }
        DB::transaction(function () use ($u, $r, $consent) {
            $consent->forceFill(['withdrawn_at' => now(), 'withdrawn_by' => $u->id])->save();
            AuditLog::record('consent.withdraw', 'referral', $r->id);
            if (! $r->isTerminal() && StatusMachine::may($u, $r, 'cancelled')) {
                self::cancel($u, $r, 'consent_withdrawn');
            }
        });
        Notifier::toFacility($r->receiving_facility_id, 'consent_withdrawn', $r);

        return $r->refresh();
    }

    public static function postMessage(User $u, Referral $r, string $body, string $kind = 'message'): Message
    {
        $level = ReferralAccess::level($u, $r);
        $side = ReferralAccess::side($u, $r);
        if (! in_array($level, [ReferralAccess::FULL, ReferralAccess::MINIMAL], true) || ! $side || $r->purged_at) {
            throw new AuthorizationException('Not allowed to message on this referral.');
        }
        if ($kind === 'info_request' && ($side !== 'receiver' || $r->isTerminal())) {
            throw new AuthorizationException('Only the receiving facility can request information on an open referral.');
        }
        if (trim($body) === '' || mb_strlen($body) > 2000) {
            throw ValidationException::withMessages(['body' => 'Write a message of up to 2,000 characters.']);
        }

        return DB::transaction(function () use ($u, $r, $body, $kind, $side) {
            $m = Message::query()->create(['referral_id' => $r->id, 'author_id' => $u->id, 'author_facility_id' => $u->facility_id, 'kind' => $kind, 'body' => $body]);
            if ($kind === 'info_request') {
                $r->forceFill(['info_requested_at' => now(), 'info_replied_at' => null])->save();
            } elseif ($side === 'referrer' && $r->info_requested_at && ! $r->info_replied_at) {
                $r->forceFill(['info_replied_at' => now()])->save();
            }
            AuditLog::record('message.post', 'referral', $r->id, ['kind' => $kind]);
            $other = $side === 'referrer' ? $r->receiving_facility_id : $r->referring_facility_id;
            Notifier::toFacility($other, $kind === 'info_request' ? 'info_requested' : 'message_received', $r);

            return $m;
        });
    }

    // ---- internals ----

    private static function requireReason(string $to, string $reason): void
    {
        $list = $to === 'declined' ? config('referralflow.decline_reasons') : config('referralflow.cancel_reasons');
        if (! array_key_exists($reason, $list)) {
            throw ValidationException::withMessages(['reason' => 'Choose a reason.']);
        }
    }

    private static function guard(User $u, Referral $r, string $to): void
    {
        // Authority first: a viewer without write standing learns nothing about which moves are legal.
        if (in_array(ReferralAccess::level($u, $r), [ReferralAccess::NONE, ReferralAccess::BREAKGLASS, ReferralAccess::TOMBSTONE], true)) {
            throw new AuthorizationException('You may not make this change.');
        }
        if (! StatusMachine::isLegal($r->status, $to)) {
            throw new InvalidTransition("Cannot move a referral from {$r->status} to {$to}.");
        }
        if (! StatusMachine::may($u, $r, $to)) {
            throw new AuthorizationException('You may not make this change.');
        }
    }

    private static function move(Referral $r, string $to, User $actor, ?string $reason = null, ?string $note = null, array $set = []): void
    {
        self::guard($actor, $r, $to);
        if (StatusMachine::requiresReason($to) && ! $reason) {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }
        $from = $r->status;
        DB::transaction(function () use ($r, $to, $actor, $reason, $note, $set, $from) {
            $r->forceFill($set + ['status' => $to])->save();
            self::event($r, $from, $to, $actor, $reason, $note);
            AuditLog::record('referral.status', 'referral', $r->id, ['from' => $from, 'to' => $to], $reason);
        });
    }

    public static function event(Referral $r, ?string $from, string $to, ?User $actor, ?string $reason = null, ?string $note = null): void
    {
        ReferralEvent::query()->create([
            'referral_id' => $r->id, 'from_status' => $from, 'to_status' => $to,
            'actor_id' => $actor?->id, 'actor_facility_id' => $actor?->facility_id,
            'reason' => $reason, 'note' => $note, 'occurred_at' => now(),
        ]);
    }
}
