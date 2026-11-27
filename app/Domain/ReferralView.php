<?php

namespace App\Domain;

use App\Models\Facility;
use App\Models\Referral;
use App\Models\User;
use App\Security\Masker;
use App\Security\ReferralAccess;

/**
 * The only thing Blade sees for a referral. Fields are filtered by access level here, so templates cannot
 * accidentally render content the viewer is not entitled to.
 */
final class ReferralView
{
    public static function build(User $u, Referral $r, string $level, bool $revealed = false): array
    {
        $names = Facility::query()->whereIn('id', [$r->referring_facility_id, $r->receiving_facility_id])->pluck('name', 'id');
        $full = in_array($level, [ReferralAccess::FULL, ReferralAccess::BREAKGLASS], true);
        $tomb = $level === ReferralAccess::TOMBSTONE;
        $side = ReferralAccess::side($u, $r);

        $v = [
            'id' => $r->id,
            'ref' => $r->displayRef(),
            'level' => $level,
            'side' => $side,
            'priority' => $r->priority,
            'status' => $r->status,
            'from' => $names[$r->referring_facility_id] ?? '?',
            'to' => $names[$r->receiving_facility_id] ?? '?',
            'sent_at' => $r->sent_at,
            'created_at' => $r->created_at,
            'appointment_at' => $r->appointment_at,
            'terminal_reason' => $r->terminal_reason,
            'overdue' => $r->escalation_level >= 3,
            'escalation_level' => $r->escalation_level,
            'info_pending' => $r->info_requested_at && ! $r->info_replied_at,
            'tombstone' => $tomb,
            'minimal' => $level === ReferralAccess::MINIMAL,
            'full' => $full,
            'purged' => (bool) $r->purged_at,
            'reason_label' => config('referralflow.reason_codes.'.$r->reason_code, $r->reason_code),
            'reason_text' => $tomb ? null : $r->reason_text,
            'sex' => $tomb ? null : $r->patient_sex,
            'age' => $tomb ? null : $r->patient_age,
            'patient' => null,
            'form' => [],
            'note' => null,
            'documents' => [],
            'counter' => null,
            'consent' => null,
            'can_reveal' => false,
        ];

        if ($full && $r->patient_id) {
            $p = $r->patient;
            $v['patient'] = [
                'masked_name' => Masker::initials($p->full_name),
                'masked_id' => Masker::identifier($p->identifier),
                'id_type' => $p->identifier_type,
                'revealed' => $revealed,
                'name' => $revealed ? $p->full_name : null,
                'dob' => $revealed ? \Illuminate\Support\Carbon::parse($p->date_of_birth)->toDateString() : null,
                'identifier' => $revealed ? $p->identifier : null,
            ];
            $v['can_reveal'] = $u->isClinical();
        }
        if ($full) {
            $schema = $r->formSchema;
            if ($schema && is_array($r->form_data)) {
                foreach ($schema->fields as $f) {
                    $val = $r->form_data[$f['key']] ?? null;
                    if ($val !== null && $val !== '') {
                        if ($f['type'] === 'boolean') {
                            $val = $val ? 'Yes' : 'No';
                        } elseif ($f['type'] === 'select') {
                            $val = collect($f['options'] ?? [])->firstWhere('value', $val)['label'] ?? $val;
                        }
                        $v['form'][] = ['label' => $f['label'], 'value' => $val];
                    }
                }
            }
            $v['note'] = $r->include_note ? $r->clinical_note : null;
            $v['documents'] = $r->attachments->values()->map(fn ($a, $i) => [
                'id' => $a->id, 'n' => $i + 1, 'mime' => $a->mime, 'size' => $a->size, 'scan' => $a->scan_status,
                'by' => $a->facility_id === $r->referring_facility_id ? $names[$r->referring_facility_id] : $names[$r->receiving_facility_id],
            ])->all();
            $c = $r->counterReferral;
            if ($c) {
                $v['counter'] = ['outcome' => config('referralflow.outcomes.'.$c->outcome, $c->outcome), 'summary' => $c->summary, 'follow_up' => $c->follow_up, 'at' => $c->created_at];
            }
        }
        $cons = $r->consent;
        if ($cons && ! $tomb) {
            $v['consent'] = ['basis' => config('referralflow.consent_bases.'.$cons->basis, $cons->basis), 'at' => $cons->recorded_at, 'withdrawn' => $cons->withdrawn_at, 'note_included' => $cons->includes_clinical_note];
        }
        $v['timeline'] = $r->events->map(fn ($e) => [
            'to' => $e->to_status, 'from' => $e->from_status, 'at' => $e->occurred_at,
            'reason' => $e->reason ? (config('referralflow.decline_reasons.'.$e->reason) ?? config('referralflow.cancel_reasons.'.$e->reason) ?? $e->reason) : null,
            'note' => ($full || $e->actor_facility_id === $u->facility_id) ? $e->note : null,
            'facility' => $e->actor_facility_id ? ($names[$e->actor_facility_id] ?? Facility::query()->whereKey($e->actor_facility_id)->value('name')) : null,
        ])->all();

        return $v;
    }
}
