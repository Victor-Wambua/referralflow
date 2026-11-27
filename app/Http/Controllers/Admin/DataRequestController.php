<?php

namespace App\Http\Controllers\Admin;

use App\Domain\DataRequestService;
use App\Http\Controllers\Controller;
use App\Models\DataRequest;
use App\Models\Patient;
use App\Security\Masker;
use Illuminate\Http\Request;

class DataRequestController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $items = DataRequest::query()->where('facility_id', $u->facility_id)->with('patient', 'requester:id,name')->orderByDesc('id')->get();
        $patients = $u->isClinical() ? Patient::query()->orderByDesc('id')->limit(50)->get()->map(fn ($p) => ['id' => $p->id, 'label' => Masker::initials($p->full_name).' · '.$p->sex.' · '.$p->ageYears()]) : collect();

        return view('data-requests', ['items' => $items, 'patients' => $patients, 'canDecide' => $u->role->value === 'facility_admin']);
    }

    public function store(Request $request)
    {
        $d = $request->validate(['patient_id' => ['required', 'integer'], 'type' => ['required', 'in:export,deletion']]);
        DataRequestService::open($request->user(), Patient::query()->findOrFail($d['patient_id']), $d['type']);

        return back()->with('status', __('Request opened. The facility admin will decide.'));
    }

    public function approve(Request $request, DataRequest $dataRequest)
    {
        abort_unless($dataRequest->facility_id === $request->user()->facility_id, 404);
        [$req, $package] = DataRequestService::approve($request->user(), $dataRequest, $request->input('note'));
        if ($package) {
            return response()->json($package, 200, ['Content-Disposition' => 'attachment; filename="data-export.json"', 'Cache-Control' => 'no-store']);
        }

        return back()->with('status', $req->status === 'blocked' ? $req->decision_note : __('Done.'));
    }

    public function reject(Request $request, DataRequest $dataRequest)
    {
        abort_unless($dataRequest->facility_id === $request->user()->facility_id, 404);
        $d = $request->validate(['note' => ['required', 'string', 'max:500']]);
        DataRequestService::reject($request->user(), $dataRequest, $d['note']);

        return back()->with('status', __('Request rejected.'));
    }
}
