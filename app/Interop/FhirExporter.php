<?php

namespace App\Interop;

use App\Models\Facility;
use App\Models\Referral;
use App\Models\User;
use App\Security\ReferralAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

/**
 * FHIR R4-SHAPED export. Not validated against any national HIE or FHIR server; no access to national
 * systems exists or is claimed. Structures are checked against JSON Schemas authored in this repository
 * (app/Interop/schemas), which are a simplified subset of R4, not the official definitions.
 */
final class FhirExporter
{
    public const NOTICE = 'FHIR-shaped, not validated against any national HIE; no access to national systems exists or is claimed';

    public const LABEL_SYSTEM = 'urn:referralflow:label';

    private const SR_STATUS = ['created' => 'draft', 'sent' => 'active', 'received' => 'active', 'accepted' => 'active', 'scheduled' => 'active', 'attended' => 'active', 'completed' => 'completed', 'declined' => 'revoked', 'cancelled' => 'revoked'];

    private const TASK_STATUS = ['created' => 'draft', 'sent' => 'requested', 'received' => 'received', 'accepted' => 'accepted', 'scheduled' => 'ready', 'attended' => 'in-progress', 'completed' => 'completed', 'declined' => 'rejected', 'cancelled' => 'cancelled'];

    private const PRIORITY = ['emergency' => 'stat', 'urgent' => 'urgent', 'routine' => 'routine'];

    public static function bundle(User $u, Referral $r): array
    {
        $level = ReferralAccess::level($u, $r);
        if ($level === ReferralAccess::NONE || $level === ReferralAccess::TOMBSTONE) {
            throw new AuthorizationException('No export access.');
        }
        $full = in_array($level, [ReferralAccess::FULL, ReferralAccess::BREAKGLASS], true);
        $entries = [];
        $add = function (array $res) use (&$entries) {
            $res['meta'] = ['tag' => [['system' => self::LABEL_SYSTEM, 'code' => 'fhir-shaped-unvalidated', 'display' => self::NOTICE]]];
            $entries[] = ['fullUrl' => 'urn:uuid:'.$res['resourceType'].'-'.$res['id'], 'resource' => $res];
        };

        $from = Facility::query()->findOrFail($r->referring_facility_id);
        $to = Facility::query()->findOrFail($r->receiving_facility_id);
        foreach ([$from, $to] as $f) {
            $add(self::organization($f));
            $add(self::location($f));
        }

        $patientRef = null;
        if ($r->patient_id) {
            $p = $r->patient;
            $patientRef = 'Patient/'.$p->id;
            $pat = ['resourceType' => 'Patient', 'id' => (string) $p->id, 'gender' => self::gender($p->sex), 'active' => true];
            if ($full) {
                $pat['name'] = [['text' => $p->full_name]];
                $pat['birthDate'] = Carbon::parse($p->date_of_birth)->toDateString();
                if ($p->identifier) {
                    $pat['identifier'] = [['system' => 'urn:referralflow:patient-identifier:'.$p->identifier_type, 'value' => $p->identifier]];
                }
            }
            $add($pat);
        }

        $sr = [
            'resourceType' => 'ServiceRequest',
            'id' => $r->id,
            'status' => self::SR_STATUS[$r->status],
            'intent' => 'order',
            'priority' => self::PRIORITY[$r->priority],
            'category' => [['text' => 'Referral']],
            'authoredOn' => ($r->sent_at ?? $r->created_at)->toIso8601String(),
            'requester' => ['reference' => 'Organization/'.$from->id],
            'performer' => [['reference' => 'Organization/'.$to->id]],
            'reasonCode' => [['text' => config('referralflow.reason_codes.'.$r->reason_code, $r->reason_code).($r->reason_text ? ': '.$r->reason_text : '')]],
            'extension' => [['url' => 'urn:referralflow:display-reference', 'valueString' => $r->displayRef()]],
        ];
        if ($patientRef) {
            $sr['subject'] = ['reference' => $patientRef];
        } else {
            $sr['subject'] = ['display' => 'Patient record removed under retention policy'];
        }
        if ($full && $r->include_note && $r->clinical_note) {
            $sr['note'] = [['text' => $r->clinical_note]];
        }
        $add($sr);

        $task = [
            'resourceType' => 'Task',
            'id' => $r->id,
            'status' => self::TASK_STATUS[$r->status],
            'businessStatus' => ['text' => $r->status],
            'intent' => 'order',
            'focus' => ['reference' => 'ServiceRequest/'.$r->id],
            'authoredOn' => $r->created_at->toIso8601String(),
            'lastModified' => $r->updated_at->toIso8601String(),
            'requester' => ['reference' => 'Organization/'.$from->id],
            'owner' => ['reference' => 'Organization/'.$to->id],
        ];
        if ($r->terminal_reason) {
            $task['statusReason'] = ['text' => $r->terminal_reason];
        }
        if ($r->appointment_at) {
            $task['restriction'] = ['period' => ['start' => $r->appointment_at->toIso8601String()]];
        }
        $add($task);

        foreach ($r->messages as $m) {
            $add([
                'resourceType' => 'Communication',
                'id' => (string) $m->id,
                'status' => 'completed',
                'about' => [['reference' => 'ServiceRequest/'.$r->id]],
                'sent' => $m->created_at->toIso8601String(),
                'sender' => ['reference' => 'Organization/'.$m->author_facility_id],
                'recipient' => [['reference' => 'Organization/'.($m->author_facility_id === $from->id ? $to->id : $from->id)]],
                'payload' => [['contentString' => $m->body]],
            ]);
        }

        if ($full) {
            foreach ($r->attachments as $a) {
                $add([
                    'resourceType' => 'DocumentReference',
                    'id' => $a->id,
                    'status' => 'current',
                    'subject' => $patientRef ? ['reference' => $patientRef] : ['display' => 'removed'],
                    'date' => $a->created_at->toIso8601String(),
                    'content' => [['attachment' => [
                        'contentType' => $a->mime,
                        'url' => '/api/v1/referrals/'.$r->id.'/documents/'.$a->id,
                        'title' => 'Attachment',
                        'size' => $a->size,
                        'hash' => base64_encode(hex2bin($a->sha1)),
                    ]]],
                    'context' => ['related' => [['reference' => 'ServiceRequest/'.$r->id]]],
                ]);
            }
        }

        return [
            'resourceType' => 'Bundle',
            'type' => 'collection',
            'timestamp' => now()->toIso8601String(),
            'meta' => ['tag' => [['system' => self::LABEL_SYSTEM, 'code' => 'fhir-shaped-unvalidated', 'display' => self::NOTICE]]],
            'entry' => $entries,
        ];
    }

    public static function organization(Facility $f): array
    {
        $o = [
            'resourceType' => 'Organization', 'id' => (string) $f->id, 'active' => (bool) $f->is_active, 'name' => $f->name,
            'type' => [['text' => $f->type]],
            'identifier' => $f->mfl_code ? [['system' => 'urn:referralflow:mfl-code-synthetic', 'value' => $f->mfl_code]] : [],
        ];
        $tel = array_values(array_filter([$f->phone ? ['system' => 'phone', 'value' => $f->phone] : null, $f->email ? ['system' => 'email', 'value' => $f->email] : null]));
        if ($tel) {
            $o['telecom'] = $tel;
        }

        return $o;
    }

    public static function location(Facility $f): array
    {
        return [
            'resourceType' => 'Location', 'id' => (string) $f->id, 'status' => $f->is_active ? 'active' : 'inactive', 'name' => $f->name,
            'managingOrganization' => ['reference' => 'Organization/'.$f->id],
            'address' => ['text' => implode(', ', array_filter([$f->ward, $f->sub_county, $f->county])), 'district' => $f->sub_county, 'state' => $f->county, 'country' => 'KE'],
            'hoursOfOperation' => $f->hours ? [['description' => $f->hours]] : [],
        ];
    }

    private static function gender(?string $s): string
    {
        return match ($s) {
            'female' => 'female', 'male' => 'male', 'other' => 'other', default => 'unknown',
        };
    }
}
