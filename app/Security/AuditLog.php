<?php

namespace App\Security;

use App\Models\AuditEntry;
use Illuminate\Support\Facades\DB;

/**
 * Append-only, hash-chained audit log. Entries hold ids and codes, never patient content.
 * hash = sha256(prev_hash | seq | occurred_at | actor | role | facility | action | subject | reason | flag | meta)
 */
final class AuditLog
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    public static function record(string $action, ?string $subjectType = null, string|int|null $subjectId = null, array $meta = [], ?string $reason = null, ?string $flag = null): AuditEntry
    {
        $u = TenantContext::user();

        return DB::transaction(function () use ($action, $subjectType, $subjectId, $meta, $reason, $flag, $u) {
            $last = AuditEntry::query()->orderByDesc('seq')->lockForUpdate()->first();
            $seq = $last ? $last->seq + 1 : 1;
            ksort($meta);
            $row = [
                'seq' => $seq,
                'occurred_at' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
                'actor_id' => $u?->id,
                'actor_role' => $u?->role?->value,
                'facility_id' => $u?->facility_id,
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId !== null ? (string) $subjectId : null,
                'reason' => $reason,
                'flag' => $flag,
                'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES) : null,
                'prev_hash' => $last ? $last->hash : self::GENESIS,
            ];
            $row['hash'] = self::hashRow($row);

            return AuditEntry::query()->withoutGlobalScopes()->create($row);
        });
    }

    public static function hashRow(array $r): string
    {
        return hash('sha256', implode('|', [
            $r['prev_hash'], $r['seq'], $r['occurred_at'], $r['actor_id'] ?? '', $r['actor_role'] ?? '',
            $r['facility_id'] ?? '', $r['action'], $r['subject_type'] ?? '', $r['subject_id'] ?? '',
            $r['reason'] ?? '', $r['flag'] ?? '', $r['meta'] ?? '',
        ]));
    }

    /** @return array{ok:bool,checked:int,broken_at:?int,message:string} */
    public static function verify(): array
    {
        $prev = self::GENESIS;
        $expectedSeq = 1;
        $checked = 0;
        foreach (DB::table('audit_entries')->orderBy('seq')->cursor() as $row) {
            $r = (array) $row;
            if ((int) $r['seq'] !== $expectedSeq) {
                return ['ok' => false, 'checked' => $checked, 'broken_at' => $expectedSeq, 'message' => "Gap or reorder at sequence $expectedSeq"];
            }
            if ($r['prev_hash'] !== $prev || self::hashRow($r) !== $r['hash']) {
                return ['ok' => false, 'checked' => $checked, 'broken_at' => (int) $r['seq'], 'message' => 'Hash mismatch at sequence '.$r['seq']];
            }
            $prev = $r['hash'];
            $expectedSeq++;
            $checked++;
        }

        return ['ok' => true, 'checked' => $checked, 'broken_at' => null, 'message' => "Chain intact ($checked entries)"];
    }
}
