<?php

namespace App\Domain;

use App\Models\Attachment;
use App\Models\CounterReferral;
use App\Models\LegalHold;
use App\Models\Message;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\ReferralEvent;
use App\Models\Setting;
use App\Security\AuditLog;
use App\Security\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Retention periods are PLACEHOLDERS (confirm with counsel; the Digital Health Act may require much longer).
 * Purge removes clinical content from closed referrals but keeps the non-identifying skeleton so dashboards
 * stay reproducible. Legal holds always win. The audit log is never purged here.
 */
final class RetentionService
{
    public static function days(string $key): int
    {
        return (int) Setting::get('retention.'.$key, config('referralflow.retention.'.$key));
    }

    public static function held(?string $referralId, ?int $patientId): bool
    {
        return LegalHold::query()->whereNull('released_at')->where(function ($q) use ($referralId, $patientId) {
            if ($referralId) {
                $q->orWhere('referral_id', $referralId);
            }
            if ($patientId) {
                $q->orWhere('patient_id', $patientId);
            }
        })->exists();
    }

    /** @return array{referrals:int,messages:int,attachments:int,drafts:int,held:int} */
    public static function purge(bool $dryRun = false): array
    {
        $out = ['referrals' => 0, 'messages' => 0, 'attachments' => 0, 'drafts' => 0, 'held' => 0];
        TenantContext::asSystem(function () use ($dryRun, &$out) {
            $closedBefore = now()->subDays(self::days('closed_referral_days'));
            $terminalDate = fn ($r) => $r->completed_at ?? $r->declined_at ?? $r->cancelled_at;

            Referral::query()->whereIn('status', ['completed', 'declined', 'cancelled'])->whereNull('purged_at')->get()
                ->each(function (Referral $r) use ($dryRun, &$out, $closedBefore, $terminalDate) {
                    $d = $terminalDate($r);
                    if (! $d || $d > $closedBefore) {
                        return;
                    }
                    if (self::held($r->id, $r->patient_id)) {
                        $out['held']++;

                        return;
                    }
                    $out['referrals']++;
                    $out['messages'] += Message::query()->withoutGlobalScopes()->where('referral_id', $r->id)->count();
                    $out['attachments'] += Attachment::query()->withoutGlobalScopes()->where('referral_id', $r->id)->whereNull('purged_at')->count();
                    if (! $dryRun) {
                        self::anonymiseReferral($r);
                        AuditLog::record('retention.purge_referral', 'referral', $r->id);
                    }
                });

            // messages and attachments on closed referrals past their own periods
            foreach (Referral::query()->whereIn('status', ['completed', 'declined', 'cancelled'])->whereNull('purged_at')->get() as $r) {
                $d = $terminalDate($r);
                if (! $d || self::held($r->id, $r->patient_id)) {
                    continue;
                }
                if ($d <= now()->subDays(self::days('message_days'))) {
                    $n = Message::query()->withoutGlobalScopes()->where('referral_id', $r->id)->count();
                    $out['messages'] += $n;
                    if (! $dryRun && $n) {
                        Message::query()->withoutGlobalScopes()->where('referral_id', $r->id)->delete();
                        AuditLog::record('retention.purge_messages', 'referral', $r->id, ['count' => $n]);
                    }
                }
                if ($d <= now()->subDays(self::days('attachment_days'))) {
                    foreach (Attachment::query()->withoutGlobalScopes()->where('referral_id', $r->id)->whereNull('purged_at')->get() as $a) {
                        $out['attachments']++;
                        if (! $dryRun) {
                            self::purgeAttachment($a);
                        }
                    }
                }
            }

            Referral::query()->where('status', 'created')->where('created_at', '<', now()->subDays(self::days('draft_days')))->get()
                ->each(function (Referral $r) use ($dryRun, &$out) {
                    $out['drafts']++;
                    if (! $dryRun) {
                        foreach (Attachment::query()->withoutGlobalScopes()->where('referral_id', $r->id)->get() as $a) {
                            Storage::disk('local')->delete($a->path);
                        }
                        $id = $r->id;
                        $r->delete();
                        AuditLog::record('retention.purge_draft', 'referral', $id);
                    }
                });
        });

        return $out;
    }

    public static function purgeAttachment(Attachment $a): void
    {
        Storage::disk('local')->delete($a->path);
        $a->forceFill(['purged_at' => now(), 'original_name' => 'purged'])->save();
        AuditLog::record('retention.purge_attachment', 'attachment', $a->id);
    }

    /** Remove clinical content, keep the non-identifying skeleton. */
    public static function anonymiseReferral(Referral $r): void
    {
        DB::transaction(function () use ($r) {
            $patientId = $r->patient_id;
            foreach (Attachment::query()->withoutGlobalScopes()->where('referral_id', $r->id)->whereNull('purged_at')->get() as $a) {
                self::purgeAttachment($a);
            }
            Message::query()->withoutGlobalScopes()->where('referral_id', $r->id)->delete();
            CounterReferral::query()->withoutGlobalScopes()->where('referral_id', $r->id)->delete();
            ReferralEvent::query()->withoutGlobalScopes()->where('referral_id', $r->id)->update(['note' => null]);
            DB::table('consents')->where('referral_id', $r->id)->update(['witness' => null, 'patient_id' => null]);
            $r->forceFill([
                'reason_text' => null, 'clinical_note' => null, 'form_data' => null, 'terminal_note' => null,
                'override_reason' => null, 'patient_id' => null, 'patient_sex' => null, 'patient_age' => null,
                'purged_at' => now(),
            ])->save();
            if ($patientId && ! Referral::query()->where('patient_id', $patientId)->exists() && ! self::held(null, $patientId)) {
                Patient::query()->withoutGlobalScopes()->whereKey($patientId)->delete();
            }
        });
    }
}
