<?php

namespace App\Http\Controllers;

use App\Directory\Verification;
use App\Models\Facility;
use App\Models\FacilityReport;
use App\Models\FacilityService;
use App\Security\AuditLog;
use Illuminate\Http\Request;

class DirectoryController extends Controller
{
    public function index(Request $request)
    {
        $q = Facility::query()->where('is_active', true)->with('services');
        if ($s = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $s).'%';
            $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('county', 'like', $like)->orWhere('sub_county', 'like', $like)
                ->orWhereHas('services', fn ($x) => $x->where('name', 'like', $like)));
        }
        if (in_array($request->query('type'), config('referralflow.facility_types'), true)) {
            $q->where('type', $request->query('type'));
        }
        $items = $q->orderBy('name')->get();
        if (in_array($v = $request->query('verification'), ['verified', 'due', 'stale', 'unverified'], true)) {
            $items = $items->filter(fn ($f) => $f->verificationState() === $v)->values();
        }

        return view('directory.index', ['items' => $items, 'types' => config('referralflow.facility_types')]);
    }

    public function show(Request $request, Facility $facility)
    {
        $u = $request->user();
        $canVerify = $this->canVerify($u, $facility);

        return view('directory.show', ['f' => $facility->load('services.verifier:id,name'), 'canVerify' => $canVerify]);
    }

    public function verify(Request $request, Facility $facility)
    {
        abort_unless($this->canVerify($request->user(), $facility), 403);
        $avail = (array) $request->input('available', []);
        foreach ($facility->services as $svc) {
            $svc->forceFill(['available' => in_array((string) $svc->id, array_map('strval', $avail), true), 'verified_at' => now(), 'verified_by' => $request->user()->id])->save();
        }
        $facility->forceFill(['info_verified_at' => now(), 'info_verified_by' => $request->user()->id])->save();
        AuditLog::record('directory.verify', 'facility', $facility->id);

        return back()->with('status', __('Marked as verified.'));
    }

    public function addService(Request $request, Facility $facility)
    {
        abort_unless($this->canVerify($request->user(), $facility), 403);
        $d = $request->validate(['name' => ['required', 'string', 'max:120']]);
        FacilityService::query()->firstOrCreate(['facility_id' => $facility->id, 'name' => $d['name']], ['available' => true]);
        AuditLog::record('directory.service_add', 'facility', $facility->id);

        return back()->with('status', __('Service added. It is unverified until you verify.'));
    }

    public function report(Request $request, Facility $facility)
    {
        $d = $request->validate(['message' => ['required', 'string', 'max:500']]);
        FacilityReport::query()->create(['facility_id' => $facility->id, 'reported_by' => $request->user()->id, 'message' => $d['message']]);
        AuditLog::record('directory.report', 'facility', $facility->id);

        return back()->with('status', __('Thanks. The facility has been told something may have changed.'));
    }

    /** JSON snapshot for the offline draft picker: facility data only, no patient data. */
    public function snapshot()
    {
        return response()->json(Facility::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'type', 'phone', 'info_verified_at'])->map(fn ($f) => [
            'id' => $f->id, 'name' => $f->name, 'type' => $f->type, 'phone' => $f->phone, 'state' => $f->verificationState(),
        ]));
    }

    private function canVerify($u, Facility $f): bool
    {
        return $u->role->value === 'platform_admin' || (in_array($u->role->value, ['coordinator', 'facility_admin'], true) && $u->facility_id === $f->id);
    }
}
