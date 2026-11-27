<?php

namespace App\Http\Controllers;

use App\Models\AuditEntry;
use App\Security\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    /** Own entries (clinician) or facility entries (coordinator, facility admin). */
    public function access(Request $request)
    {
        $u = $request->user();
        $q = AuditEntry::query()->orderByDesc('seq');
        $u->role->value === 'clinician' ? $q->where('actor_id', $u->id) : $q->where('facility_id', $u->facility_id);
        $items = $q->paginate(30);

        return view('access', ['items' => $items, 'scope' => $u->role->value === 'clinician' ? 'your own activity' : 'your facility']);
    }

    public function explorer(Request $request)
    {
        $q = AuditEntry::query()->orderByDesc('seq');
        if ($a = $request->query('action')) {
            $q->where('action', 'like', str_replace(['%', '_'], ['\\%', '\\_'], (string) $a).'%');
        }
        if ($f = $request->query('flag')) {
            $q->where('flag', $f);
        }
        $verify = $request->boolean('verify') ? AuditLog::verify() : null;

        return view('admin.audit', ['items' => $q->paginate(40)->withQueryString(), 'verify' => $verify]);
    }

    public function export(Request $request)
    {
        AuditLog::record('audit.export', 'audit', null, [], null, 'export');
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['seq', 'occurred_at', 'actor_id', 'role', 'facility_id', 'action', 'subject_type', 'subject_id', 'flag', 'hash'], ',', '"', '');
        AuditEntry::query()->orderBy('seq')->limit(10000)->each(fn ($e) => fputcsv($out, [$e->seq, $e->occurred_at, $e->actor_id, $e->actor_role, $e->facility_id, $e->action, $e->subject_type, $e->subject_id, $e->flag, $e->hash], ',', '"', ''));
        rewind($out);

        return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="audit-export.csv"']);
    }
}
